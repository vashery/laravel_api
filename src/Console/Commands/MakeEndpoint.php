<?php

namespace uhin\laravel_api\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeEndpoint extends GeneratorCommand
{
    use BaseCommand;

    private $stub = null;
    private $namespace = null;
    private $nameInput = null;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uhin:make:endpoint {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . $this->namespace;
    }

    protected function getStub()
    {
        return $this->stub;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $model = $this->argument('name');

        // Model
        $this->call('make:model', [
            'name' => "Models/{$model}",
        ]);

        // Migration
        $this->call('make:migration', [
            'name' => "create_" . snake_case(str_plural($model)) . "_table",
        ]);

        // Factory
        $this->call('make:factory', [
            'name' => "{$model}Factory",
            '--model' => "Models/{$model}",
        ]);

        // Seeder
        $this->call('make:seed', [
            'name' => str_plural($model) . "TableSeeder",
        ]);
        // Make a call to the new seeder in the DatabaseSeeder.php file
        $seederCall = '$this->call(' . str_plural($model) . 'TableSeeder::class);';
        $seeder = database_path('seeds/DatabaseSeeder.php');
        $contents = file_get_contents($seeder);
        if (!str_contains($contents, $seederCall)) {
            $contents = preg_replace('/(function\s+run.*?\{)(.*?)(\})/s', '${1}${2}' . PHP_EOL . '        ' . $seederCall . PHP_EOL . '    ${3}', $contents);
            file_put_contents($seeder, $contents);
        }

        // Resource
        $this->stub = __DIR__ . '/stubs/resource.stub';
        $this->namespace = '\Http\Resources';
        $this->type = 'Resource';
        $this->nameInput = $model . 'Resource';
        parent::handle();

        // Controller
        $this->stub = __DIR__ . '/stubs/controller.stub';
        $this->namespace = '\Http\Controllers';
        $this->type = 'Controller';
        $this->nameInput = $model . 'Controller';
        parent::handle();

        // Reset the name input
        $this->nameInput = null;

        // Routes
        $destination = base_path('routes/api.php');
        $name = $this->qualifyClass($this->getNameInput());
        $stub = file_get_contents(__DIR__ . '/stubs/endpoint-routes.stub');
        $stub = $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
        $stub = $this->replaceResourceType($stub, $name);
        file_put_contents($destination, PHP_EOL . $stub . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in base namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);
        return $this->replaceResourceType($stub, $name);
    }

    /**
     * Replace the resource type in the Resource.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceResourceType($stub, $name)
    {
        $stub = str_replace('DummyTypePlural', str_replace('_', '-', snake_case(str_plural($this->argument('name')))), $stub);
        $stub = str_replace('DummyType', str_replace('_', '-', snake_case($this->argument('name'))), $stub);
        $stub = str_replace('DummyModelVariable', lcfirst(class_basename($this->argument('name'))), $stub);
        $stub = str_replace('DummyModel', $this->argument('name'), $stub);
        return $stub;
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        if ($this->nameInput !== null) {
            return $this->nameInput;
        } else {
            return parent::getNameInput();
        }
    }

}
