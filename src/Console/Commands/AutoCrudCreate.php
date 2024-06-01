<?php

namespace Api\LaravelAutocrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;


/**
 * Class AutoCrudCreate
 *
 * @category Console_Command
 * @package  App\Console\Commands
 * @author   Abhishek Dixit <abhishekdixit342@gmail.com>
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * 
 * @date     YYYY-MM-DD
 *
 * This command creates CRUD operations for a specific resource
 * with specified columns and types.
 */
class AutoCrudCreate extends Command
{
    protected $signature = 'autocrud:api {resource} {--columns=}';
    protected $description = 'Create CRUD operations for a specific resource with specified columns and types';

     /**
     * ssupported column type in laravel
     * @return array
     */
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
        $controllerPath = app_path("Http\\Controllers\\" . $resource . "Controller");

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

            $validatedColumns[] = compact('name', 'type');
        }

        return $validatedColumns;
    }

    protected function getModelPath(string $name): string
    {
        $modelDirectory = app_path('Models') ?: app_path();
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
        $modelNamespace = "App\\Models\\{$modelName}";
        $resourceNamespace = "App\\Http\\Resources\\{$resource}Resource";
        $collectionNamespace = "App\\Http\\Resources\\{$resource}Collection";

        $controllerContent = <<<CONTROLLER
        <?php

        namespace App\Http\Controllers;

        use Illuminate\Http\Request;
        use {$modelNamespace}; // Import model
        use {$resourceNamespace}; // Import resource
        use {$collectionNamespace}; // Import collection

        class {$controllerName} extends Controller
        {
            /**
             * Display a listing of the resource.
             *
             * @param  \Illuminate\Http\Request  \$request
             * @return \Illuminate\Http\Response
             */
            public function index(Request \$request)
            {
                // Retrieve and paginate models
                \$models = {$modelName}::filter(\$request->all())->paginate(10);
                
                // Return resource collection
                return new {$resource}Collection(\$models);
            }

            /**
             * Store a newly created resource in storage.
             *
             * @param  \Illuminate\Http\Request  \$request
             * @return \Illuminate\Http\Response
             */
            public function store(Request \$request)
            {
                // Create new model instance
                \$model = {$modelName}::create(\$request->all());
                
                // Return resource
                return new {$resource}Resource(\$model);
            }

            /**
             * Display the specified resource.
             *
             * @param  int  \$id
             * @return \Illuminate\Http\Response
             */
            public function show(\$id)
            {
                // Find model by ID
                \$model = {$modelName}::findOrFail(\$id);
                
                // Return resource
                return new {$resource}Resource(\$model);
            }

            /**
             * Update the specified resource in storage.
             *
             * @param  \Illuminate\Http\Request  \$request
             * @param  int  \$id
             * @return \Illuminate\Http\Response
             */
            public function update(Request \$request, \$id)
            {
                // Find model by ID
                \$model = {$modelName}::findOrFail(\$id);
                
                // Update model
                \$model->update(\$request->all());
                
                // Return updated resource
                return new {$resource}Resource(\$model);
            }

            /**
             * Remove the specified resource from storage.
             *
             * @param  int  \$id
             * @return \Illuminate\Http\Response
             */
            public function destroy(\$id)
            {
                // Find model by ID
                \$model = {$modelName}::findOrFail(\$id);
                
                // Delete model
                \$model->delete();
                
                // Return response
                return response()->noContent();
            }
        }
        CONTROLLER;

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
        $latestFile = collect($migrationFiles)->sortByDesc(fn($file) => $file->getMTime())->first();

        return $latestFile ? $latestFile->getPathname() : null;
    }

    protected function getTableDefinition(array $columnsArray): string
    {
        $schemaFields = '';
        foreach ($columnsArray as $column) {
            $schemaFields .= "\n            \$table->{$column['type']}('{$column['name']}');";
        }
        return ltrim($schemaFields, " \t\n\r\0\x0B");
    }

    protected function updateModelFile(string $modelPath, array $columnsArray): void
    {
        if (File::exists($modelPath)) {
            $modelContent = File::get($modelPath);
            $columnNames = [];
            $filterMethod = '';
            foreach ($columnsArray as $column) {
                $columnNames[] = $column['name'];

                if ($column['type'] === 'json') {
                    $filterMethod .= <<<EOT
                        
                            /**
                             * Set the {$column['name']} attribute as JSON.
                             *
                             * @param  mixed  \$value
                             * @return void
                             */
                            public function set{$column['name']}Attribute(\$value)
                            {
                                \$this->attributes['{$column['name']}'] = json_encode(\$value);
                            }
                        
                            /**
                             * Get the {$column['name']} attribute as decoded JSON.
                             *
                             * @param  string  \$value
                             * @return mixed
                             */
                            public function get{$column['name']}Attribute(\$value)
                            {
                                return json_decode(\$value, true);
                            }
                        EOT;
                }
            }
            $fillable = "protected \$fillable = ['" . implode("', '", $columnNames) . "'];";
            if (!empty($filterMethod)) $filterMethod .= "\n";
            $filterMethod .= <<<'EOD'
                /**
                 * Filter data by fillable.
                 *
                 * @return mixed
                 */
                public function scopeFilter($query, array $filters)
                {
                    foreach ($filters as $field => $value) {
                        if (in_array($field, $this->fillable) && !empty($value)) {
                            $query->where($field, $value);
                        }
                    }
                    return $query;
                }
            EOD;

            $modelContent = str_replace(
                'use Illuminate\Database\Eloquent\Model;',
                "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\SoftDeletes;",
                $modelContent
            );
            $modelContent = str_replace(
                'use HasFactory;',
                "use HasFactory, SoftDeletes;",
                $modelContent
            );

            $modelContent = preg_replace('/\}\s*$/', "\n    {$fillable}\n    {$filterMethod}\n}", $modelContent);

            File::put($modelPath, $modelContent);
            $this->info("Model " . class_basename($modelPath) . " updated successfully.");
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
            $resourceContent = File::get($resourcePath);
            $resourceContent = $this->updateResourceFile($resourceContent, $columnsArray);
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

    protected function updateResourceFile(string $resourceContent, array $columnsArray): string
    {
        $resourceFields = '';
        foreach ($columnsArray as $column) {
            $resourceFields .= "'{$column['name']}' => \$this->{$column['name']},\n                ";
        }

        $resourceFields = rtrim($resourceFields, ",\n");

        return preg_replace(
            '/return parent::toArray\(\$request\);/',
            "if (empty(\$this->resource)) {
                return [];
            }\n\t\t\treturn [
                {$resourceFields}
            ];",
            $resourceContent
        );
    }
}
