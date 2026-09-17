<?php

namespace Tests\Unit;

use App\Nexus\NexusToolContext;
use App\Nexus\Tools\CodeIntelligenceTool;
use Tests\TestCase;

class CodeIntelligenceToolTest extends TestCase
{
    public function test_inspects_php_javascript_typescript_and_python_without_writing(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nexus-code-'.uniqid();
        mkdir($root.'/src', 0777, true);
        file_put_contents($root.'/src/Example.php', "<?php\nuse App\\\\Models\\\\User;\ninterface Contract {}\nclass Example { public function run() {} }\n");
        file_put_contents($root.'/src/app.ts', "import { api } from './api'; interface Client {} class App { run() {} }\n");
        file_put_contents($root.'/src/app.js', "import x from './x'; function load() {}\n");
        file_put_contents($root.'/src/script.py', "class Worker:\n    def start(self):\n        pass\n");

        try {
            $result = (new CodeIntelligenceTool($root))->execute(
                [],
                new NexusToolContext(system: true)
            );

            $this->assertTrue($result->successful);
            $this->assertTrue($result->data['project']['read_only']);
            $this->assertCount(4, $result->data['files']);
            $this->assertContains('class', array_column($result->data['symbols'], 'kind'));
            $this->assertContains('interface', array_column($result->data['symbols'], 'kind'));
            $this->assertContains('function', array_column($result->data['symbols'], 'kind'));
            $this->assertNotEmpty($result->data['imports']);
            $this->assertContains('source', array_column($result->data['files'], 'component'));
        } finally {
            foreach (glob($root.'/src/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($root.'/src');
            rmdir($root);
        }
    }

    public function test_rejects_file_path_as_project(): void
    {
        $root = tempnam(sys_get_temp_dir(), 'nexus-code-');
        try {
            $result = (new CodeIntelligenceTool(dirname($root)))->execute(
                ['path' => basename($root)],
                new NexusToolContext(system: true)
            );
            $this->assertFalse($result->successful);
            $this->assertSame('project_path_not_directory', $result->errorCode);
        } finally {
            unlink($root);
        }
    }
}
