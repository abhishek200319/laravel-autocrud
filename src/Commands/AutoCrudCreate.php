<?php

namespace Api\LaravelAutocrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class AutoCrudCreate extends Command
{
    protected $signature = 'autocrud:api {resource} {--columns=}';
    protected $description = 'Create CRUD operations for a specific resource with specified columns and types';

    protected $supportedColumnTypes = [
        'bigIncrements', 'bigInteger', 'binary', 'boolean', 'char', 'date', 'dateTime', 'dateTimeTz', 'decimal', 'double', 'enum', 'float', 'foreignId', 'foreignUuid', 'geometry', 'geometryCollection', 'increments', 'integer', 'ipAddress', 'json', 'jsonb', 'lineString', 'longText', 'macAddress', 'mediumIncrements', 'mediumInteger', 'mediumText', 'morphs', 'multiLineString', 'multiPoint', 'multiPolygon', 'nullableMorphs', 'nullableUuidMorphs', 'point', 'polygon', 'rememberToken', 'set', 'smallIncrements', 'smallInteger', 'softDeletes', 'softDeletesTz', 'string', 'text', 'time', 'timeTz', 'timestamp', 'timestampTz', 'tinyIncrements', 'tinyInteger', 'tinyText', 'unsignedBigInteger', 'unsignedDecimal', 'unsignedInteger', 'unsignedMediumInteger', 'unsignedSmallInteger', 'unsignedTinyInteger', 'uuid', 'uuidMorphs', 'year'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $resource = $this->argument('resource');
        $columns = $this->option('columns');

        if (!$resource || !$columns) {
            $this->error('Resource name and columns are required.');
            return;
        }

        $steps = 7; // Total number of steps in the process
        $this->output->progressStart($steps);

        $modelPath = $this->getModelPath($resource);
        $controllerPath = app_path("Http/Controllers/{$resource}Controller.php");

        if ($this->fileExists($modelPath) || $this->fileExists($controllerPath)) {
            $this->error("Resource {$resource} already exists!");
            $this->output->progressFinish();
            return;
        }

        $this->output->progressAdvance();

        $columnsArray = $this->parseAndValidateColumns($columns);
        if (!$columnsArray) {
            $this->output->progressFinish();
            return;
        }

        $this->createModelWithMigration($resource, $columnsArray);
        $this->output->progressAdvance();

        $this->createController($resource);
        $this->output->progressAdvance();

        $this->updateMigrationFile($resource, $columnsArray);
        $this->output->progressAdvance();

        $this->addRoute($resource);
        $this->output->progressAdvance();

        $this->createResource($resource, $columnsArray);
        $this->output->progressAdvance();

        $this->createCollection($resource);
        $this->output->progressAdvance();

        $this->output->progressFinish();
        $this->info('CRUD operations created successfully.');
    }

    protected function parseAndValidateColumns(string $columns): ?array
    {
        $columnsArray = explode(',', $columns);
        $validatedColumns = [];

        foreach ($columnsArray as $column) {
            [$name, $type] = explode(':', $column);

            if (!in_array($type, $this->supportedColumnTypes)) {
                $this->error("Invalid column type: {$type}. Supported types are: " . implode(', ', $this->supportedColumnTypes));
                return null;
            }

            $validatedColumns[$name] = $type;
        }

        return $validatedColumns;
    }

    protected function getModelPath(string $name): string
    {
        $modelDirectory = app_path('Models');
    
        // Check if the Models directory exists, otherwise use the app directory
        if (!is_dir($modelDirectory)) {
            $modelDirectory = app_path();
        }
    
        return "{$modelDirectory}/{$name}.php";
    }

    protected function fileExists(string $filePath): bool
    {
        return File::exists($filePath);
    }

    protected function createModelWithMigration(string $name, array $columnsArray): void
    {
        Artisan::call('make:model', ['name' => $name, '--migration' => true]);
        $modelPath = $this->getModelPath($name);
        $this->updateModelFile($modelPath, $columnsArray);
        $this->info("Model {$name} and migration created successfully.");
    }

    protected function createController(string $resource): void
    {
        Artisan::call('make:controller', ['name' => "{$resource}Controller", '--resource' => true]);
        $this->updateControllerFile($resource);
        $this->info("Controller {$resource}Controller created and updated successfully.");
    }

    protected function updateControllerFile(string $resource): void
    {
        $controllerName = "{$resource}Controller";
        $controllerPath = app_path("Http/Controllers/{$controllerName}.php");
        $modelName = Str::singular($resource);
        $modelPath = $this->getModelPath($modelName);
        $modelNamespace = $this->getModelNamespace($modelPath);
        $resourceNamespace = "App\\Http\\Resources\\{$resource}Resource";
        $collectionNamespace = "App\\Http\\Resources\\{$resource}Collection";

        $controllerStub = File::get(resource_path('stubs/controller.stub'));
        $controllerContent = $this->replacePlaceholders($controllerStub, [
            'controllerName' => $controllerName,
            'modelName' => $modelName,
            'modelNamespace' => $modelNamespace,
            'resourceNamespace' => $resourceNamespace,
            'collectionNamespace' => $collectionNamespace,
            'resourceName' => $resource,
        ]);

        File::put($controllerPath, $controllerContent);
    }

    protected function updateMigrationFile(string $resource, array $columnsArray): void
    {
        $migrationPath = $this->getLatestMigrationPath();

        if ($migrationPath) {
            $migrationContent = File::get($migrationPath);
            $tableDefinition = $this->getTableDefinition($columnsArray);

            $migrationContent = str_replace(
                'use Illuminate\Support\Facades\Schema;',
                "use Illuminate\Support\Facades\Schema;\nuse Illuminate\Database\Eloquent\SoftDeletes;",
                $migrationContent
            );

            $migrationContent = preg_replace('/(class .*? extends Migration)\s*\{/', "$1\n{\n    use SoftDeletes;\n", $migrationContent, 1);

            $migrationContent = str_replace(
                '$table->timestamps();',
                $tableDefinition . PHP_EOL . '            $table->timestamps();' . PHP_EOL . '            $table->softDeletes();',
                $migrationContent
            );

            File::put($migrationPath, $migrationContent);
            $this->info("Columns added to migration for {$resource}.");
        } else {
            $this->error("Migration file for {$resource} not found.");
        }
    }

    protected function addRoute(string $resource): void
    {
        $route = "Route::resource('" . Str::kebab(Str::plural($resource)) . "', 'App\\Http\\Controllers\\{$resource}Controller');";
        $routeFile = base_path('routes/api.php');

        if (File::append($routeFile, "\n" . $route)) {
            $this->info('Route added successfully.');
        } else {
            $this->error('Failed to add route.');
        }
    }

    protected function getLatestMigrationPath(): ?string
    {
        $migrationFiles = File::files(database_path('migrations'));
        $latestFile = collect($migrationFiles)->sortByDesc(function ($file) {
            return $file->getMTime();
        })->first();

        return $latestFile ? $latestFile->getPathname() : null;
    }

    protected function getTableDefinition(array $columnsArray): string
    {
        $schemaFields = '';
        foreach ($columnsArray as $name => $type) {
            $schemaFields .= "\n            \$table->{$type}('{$name}');";
        }
        return ltrim($schemaFields, " \t\n\r\0\x0B");
    }

    protected function updateModelFile(string $modelPath, array $columnsArray): void
    {
        if (File::exists($modelPath)) {
            $fillable = "'" . implode("', '", array_keys($columnsArray)) . "'";
            $modelStub = File::get(resource_path('stubs/model.stub'));
            $modelNamespace = $this->getModelNamespace($modelPath);
            $modelName = str_replace(".php","",class_basename($modelPath)); // Remove extension from class name
            $modelNamespace = str_replace("\\$modelName","",$this->getModelNamespace($modelPath));
            $modelContent = $this->replacePlaceholders($modelStub, [
                'modelName' => $modelName,
                'fillableColumns' => $fillable,
                'Namespace' => $modelNamespace,
            ]);
    
            File::put($modelPath, $modelContent);
            $this->info("Model {$modelName} updated successfully.");
        } else {
            $this->error("Model file {$modelPath} not found.");
        }
    }
    
    

    protected function createResource(string $resource, array $columnsArray): void
    {
        $resourceName = "{$resource}Resource";
        Artisan::call('make:resource', ['name' => $resourceName]);
        $resourcePath = app_path("Http/Resources/{$resourceName}.php");

        if (File::exists($resourcePath)) {
            $resourceStub = File::get(resource_path('stubs/resource.stub'));
            $resourceContent = $this->updateResourceFile($resourceStub, $columnsArray, $resourceName);
            File::put($resourcePath, $resourceContent);
            $this->info("Resource {$resourceName} created and updated successfully.");
        } else {
            $this->error("Resource file {$resourceName} not found.");
        }
    }

    protected function createCollection(string $resource): void
    {
        $collectionName = "{$resource}Collection";
        Artisan::call('make:resource', ['name' => $collectionName]);
        $this->info("Collection {$collectionName} created successfully.");
    }

    protected function updateResourceFile(string $resourceContent, array $columnsArray, string $resourceName): string
    {
        $resourceFields = '';
        foreach ($columnsArray as $name => $type) {
               $resourceFields .= "'{$name}' => \$this->{$name},\n            ";
        }

        $resourceFields = rtrim($resourceFields, ",\n");
        return $this->replacePlaceholders($resourceContent, [
            'resourceName' => $resourceName,
            'resourceFields' => $resourceFields,
        ]);
    }

    protected function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $placeholder => $value) {
            $content = str_replace("{{{$placeholder}}}", $value, $content);
        }
        return $content;
    }

    public function getModelNamespace(string $modelPath)
    {
        $relativePath = str_replace([app_path(), '.php'], '', $modelPath);
        $relativePath = str_replace('/', '\\', $relativePath);
        \Log::info($relativePath);
        return 'App' . $relativePath;
    }
    
}
