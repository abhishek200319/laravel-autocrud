<?php

namespace Api\LaravelAutocrud\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class AutoCrudCreateTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function getPackageProviders($app)
    {
        return [
            \Api\LaravelAutocrud\AutoCrudServiceProvider::class,
        ];
    }
    /**
     * Test AutoCrudCreate command with different Laravel versions.
     *
     * @dataProvider laravelVersionsProvider
     * @param string $laravelVersion
     * @return void
     */
    public function testAutoCrudCreateWithDifferentLaravelVersions($laravelVersion)
    {
        $resource = 'TestResource';
        $columns = 'name:string,email:string';

        putenv("LARAVEL_VERSION={$laravelVersion}");

        $this->artisan('autocrud:api', [
            'resource' => $resource,
            '--columns' => $columns
        ])->assertExitCode(0);

        // Assert expected files are created and contain correct content
        $this->assertModelFileContainsString($resource, "class {$resource} extends Model");
        $this->assertMigrationFileContainsString($resource, "Schema::create('{$this->pluralizeResource($resource)}', function (Blueprint \$table) {");
        $this->assertControllerFileContainsString($resource, "class {$resource}Controller extends Controller");
        $this->assertRouteFileContainsResourceRoute($resource, "Route::resource('test-resources', 'App\Http\Controllers\\{$resource}Controller');");
        $this->assertResourceFileContainsString($resource, "class {$resource}Resource extends JsonResource");
        $this->assertCollectionFileContainsString($resource, "class {$resource}Collection extends ResourceCollection");
        $this->assertModelFileContainsProperties($resource, $this->getPropertiesFromColumns($columns));
        $this->assertMigrationFileContainsColumns($resource, $this->getColumnsArray($columns));
        $this->assertResourceFileContainsAttributes($resource, $this->getPropertiesFromColumns($columns));

        // Additional assertions for database testing
        $migrationFileName = $this->findMigrationFileName($resource);
        $this->assertNotEmpty($migrationFileName);
        $this->assertFileExistsAndContains(database_path("migrations/{$migrationFileName}"), "Schema::create('{$this->pluralizeResource($resource)}', function (Blueprint \$table) {");
}

    protected function findMigrationFileName($resource)
    {
        $migrationFiles = File::glob(database_path('migrations/*_create_' . $this->pluralizeResource($resource) . '_table.php'));
        if (!empty($migrationFiles)) {
            // Sort the files by timestamp to get the latest migration
            usort($migrationFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            return basename($migrationFiles[0]);
        }
        return null;
    }

    /**
     * Data provider for different Laravel versions.
     *
     * @return array
     */
    public function laravelVersionsProvider()
    {
        return [
            ['6.*'],
            ['7.*'],
            ['8.*'],
            ['^9.0'],
            ['^10.0'],
        ];
    }

    protected function assertModelFileContainsString($resource, $expectedString)
    {
        $modelDirectory = app_path('Models') ?: app_path();
        $filePath = "{$modelDirectory}/{$resource}.php";
        $this->assertFileExistsAndContains($filePath, $expectedString);
    }

    protected function assertMigrationFileContainsString($resource, $expectedString)
    {
        $migrationFiles = File::glob(database_path('migrations/*_create_' . $this->pluralizeResource($resource) . '_table.php'));
        $this->assertNotEmpty($migrationFiles);
        foreach ($migrationFiles as $migrationFile) {
            $this->assertFileExistsAndContains($migrationFile, $expectedString);
        }
    }

    protected function assertControllerFileContainsString($resource, $expectedString)
    {
        $filePath = app_path("Http/Controllers/{$resource}Controller.php");
        $this->assertFileExistsAndContains($filePath, $expectedString);
    }

    protected function assertRouteFileContainsResourceRoute($resource)
    {
        $routeName = Str::kebab(Str::plural($resource));
        $expectedString = "Route::resource('$routeName', 'App\Http\Controllers\\{$resource}Controller');";
        $routeContent = File::get(base_path('routes/api.php'));
        $this->assertStringContainsString($expectedString,$routeContent);
    }

    protected function assertResourceFileContainsString($resource, $expectedString)
    {
        $resourceFilePath = app_path("Http/Resources/{$resource}Resource.php");
        $this->assertFileExistsAndContains($resourceFilePath, $expectedString);
    }

    protected function assertCollectionFileContainsString($resource, $expectedString)
    {
        $collectionFilePath = app_path("Http/Resources/{$resource}Collection.php");
        $this->assertFileExistsAndContains($collectionFilePath, $expectedString);
    }
    

    protected function assertFileExistsAndContains($filePath, $expectedString)
    {
        $this->assertFileExists($filePath);
        $fileContents = File::get($filePath);
        $this->assertStringContainsString($expectedString, $fileContents);
    }

    protected function pluralizeResource($resource)
    {
        return Str::plural(Str::snake($resource));
    }

    protected function tearDown(): void
    {
        $resource = 'TestResource';
        File::delete([
            app_path("Models/{$resource}.php"),
            ...File::glob(database_path('migrations/*_create_' . $this->pluralizeResource($resource) . '_table.php')),
            app_path("Http/Controllers/{$resource}Controller.php"),
            app_path("Http/Resources/{$resource}Resource.php"),
            app_path("Http/Resources/{$resource}Collection.php"),
        ]);
        $this->removeRouteFromApiFile($resource);
        parent::tearDown();
    }

    protected function getColumnsArray($columns)
    {
        return array_reduce(explode(',', $columns), function ($carry, $item) {
            [$name, $type] = explode(':', $item);
            $carry[$name] = $type;
            return $carry;
        }, []);
    }
    
    
    protected function getPropertiesFromColumns($columns)
    {
        return array_map(function ($column) {
            return explode(':', $column)[0];
        }, explode(',', $columns));
    }

    protected function assertMigrationFileContainsColumns($resource, $columns)
    {
        $migrationFiles = File::glob(database_path('migrations/*_create_' . $this->pluralizeResource($resource) . '_table.php'));
        $this->assertNotEmpty($migrationFiles);
    
        foreach ($migrationFiles as $migrationFile) {
            $fileContents = File::get($migrationFile);
            // Check if each column is present in the migration file
            foreach ($columns as $name => $type) {
                $this->assertStringContainsString("\$table->{$type}('$name')", $fileContents);
            }
            
        }
    }
    
    
    protected function assertModelFileContainsProperties($resource, $properties)
    {
        $modelDirectory = app_path('Models') ?: app_path();
        $filePath = "{$modelDirectory}/{$resource}.php";
        $this->assertFileExists($filePath);
    
        $fileContents = File::get($filePath);
    
        // Check if each property is present in the model file
        $this->assertStringContainsString('protected $fillable', $fileContents);
    }
    
    protected function assertResourceFileContainsAttributes($resource, $attributes)
    {
        $resourceFilePath = app_path("Http/Resources/{$resource}Resource.php");
        $this->assertFileExists($resourceFilePath);
    
        $fileContents = File::get($resourceFilePath);
    
        // Check if each attribute is present in the resource file
        foreach ($attributes as $attribute) {
            $this->assertStringContainsString("'{$attribute}' => \$this->{$attribute},", $fileContents);
        }
    }

    protected function removeRouteFromApiFile($resource)
    {
        $apiFilePath = base_path('routes/api.php');
    
        // Check if the file exists
        if (!file_exists($apiFilePath)) {
            throw new \RuntimeException("API routes file not found: {$apiFilePath}");
        }
    
        // Read the contents of the API routes file
        $apiFileContents = file_get_contents($apiFilePath);
        
        // Generate the route name dynamically based on the resource
        $routeName = Str::kebab(Str::plural($resource));

        // Escape special characters in the route name
        $escapedRouteName = preg_quote($routeName, '~');
        
        // Construct the expected string for the route
        $expectedString = "Route::resource('$escapedRouteName', 'App\Http\Controllers\{$resource}Controller');";
        
        // Remove the specific route
        $apiFileContents = preg_replace("~$expectedString~", '', $apiFileContents); 
    
    
        // Write the modified contents back to the file
        file_put_contents($apiFilePath, $apiFileContents);
    }
    
    
    
}
