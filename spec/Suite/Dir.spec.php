<?php
namespace Lead\Dir\Spec\Suite;

use Lead\Dir\Dir;
use Exception;

describe("Dir", function() {

    $this->normalize = function($path) {
        if (!is_array($path)) {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        $result = [];
        foreach ($path as $p) {
            $result[] = $this->normalize($p);
        }
        return $result;
    };

    describe("::scan()", function() {

        $sort = function($files) {
            sort($files);
            return $files;
        };

        beforeEach(function() {
            $this->path = 'spec/Fixture';
        });

        it("scans files", function() {

            $files = Dir::scan($this->path, [
                'type' => 'file',
                'recursive' => false
            ]);
            expect($files)->toBe($this->normalize(['spec/Fixture/file1.txt']));

        });

        it("scans and show dots", function() use ($sort) {

            $files = Dir::scan($this->path, [
                'skipDots' => false,
                'recursive' => false
            ]);

            expect($sort($files))->toBe($sort($this->normalize([
                'spec/Fixture/.',
                'spec/Fixture/..',
                'spec/Fixture/file1.txt',
                'spec/Fixture/Nested',
                'spec/Fixture/Extensions'
            ])));

        });

        it("scans and follow symlinks", function() use ($sort) {

            $files = Dir::scan($this->path . DIRECTORY_SEPARATOR . 'Extensions', [
                'followSymlinks' => false,
                'recursive' => false
            ]);

            expect($sort($files))->toBe($this->normalize([
                'spec/Fixture/Extensions/Childs',
                'spec/Fixture/Extensions/file.xml',
                'spec/Fixture/Extensions/index.html',
                'spec/Fixture/Extensions/index.php'
            ]));

        });

        it("scans files recursively", function() use ($sort) {

            $files = Dir::scan($this->path . DIRECTORY_SEPARATOR . 'Nested', [
                'type' => 'file'
            ]);

            expect($sort($files))->toBe($this->normalize([
                'spec/Fixture/Nested/Childs/child1.txt',
                'spec/Fixture/Nested/nested_file1.txt',
                'spec/Fixture/Nested/nested_file2.txt'
            ]));

        });

        it("scans files & directores recursively", function() use ($sort) {

            $files = Dir::scan($this->path . DIRECTORY_SEPARATOR . 'Nested');

            expect($sort($files))->toBe($this->normalize([
                'spec/Fixture/Nested/Childs',
                'spec/Fixture/Nested/Childs/child1.txt',
                'spec/Fixture/Nested/nested_file1.txt',
                'spec/Fixture/Nested/nested_file2.txt'
            ]));

        });

        it("scans only leaves recursively", function() use ($sort) {

            $files = Dir::scan($this->path. DIRECTORY_SEPARATOR . 'Nested', [
                'leavesOnly' => true
            ]);

            expect($sort($files))->toBe($this->normalize([
                'spec/Fixture/Nested/Childs/child1.txt',
                'spec/Fixture/Nested/nested_file1.txt',
                'spec/Fixture/Nested/nested_file2.txt'
            ]));

        });

        it("scans txt files recursively", function() use ($sort) {

            $files = Dir::scan($this->path, [
                'include' => '*.txt',
                'type' => 'file'
            ]);

            expect($sort($files))->toBe($this->normalize([
                'spec/Fixture/Extensions/Childs/child1.txt',
                'spec/Fixture/Nested/Childs/child1.txt',
                'spec/Fixture/Nested/nested_file1.txt',
                'spec/Fixture/Nested/nested_file2.txt',
                'spec/Fixture/file1.txt'
            ]));

        });

        it("scans non nested txt files recursively", function() use ($sort) {

            $files = Dir::scan($this->path, [
                'include' => '*.txt',
                'exclude' => '*Nested*',
                'type' => 'file'
            ]);

            expect($sort($files))->toBe($this->normalize([
                'spec/Fixture/Extensions/Childs/child1.txt',
                'spec/Fixture/file1.txt'
            ]));

        });

        it("throws an exception if the path is invalid", function() {

            $closure = function() {
                Dir::scan('Non/Existing/Path', [
                    'type' => 'file',
                    'recursive' => false
                ]);
            };
            expect($closure)->toThrow(new Exception());

        });

        it("returns itself when the path is a file", function() {

            $files = Dir::scan('spec/Fixture/file1.txt', [
                'include' => '*.txt',
                'exclude' => '*nested*',
                'type' => 'file'
            ]);
            expect($files)->toBe($this->normalize(['spec/Fixture/file1.txt']));

        });

    });

    describe("::copy()", function() {

        beforeEach(function() {
            $this->tmpDir = Dir::tempnam(sys_get_temp_dir(), 'spec');
        });

        afterEach(function() {
            Dir::remove($this->tmpDir, ['recursive' => true]);
        });

        it("copies a directory recursively", function() {

            Dir::copy('spec/Fixture', $this->tmpDir);

            $paths = Dir::scan('spec/Fixture');

            foreach ($paths as $path) {
                $target = preg_replace('~^spec~', '', $path);
                expect(file_exists($this->tmpDir . $target))->toBe(true);
            }

        });

        it("copies a directory using a custom copy handler", function() {

            Dir::copy('spec/Fixture', $this->tmpDir, [
                'copyHandler' => function($path, $target) {
                    copy($path, $target . '.bak');
                }
            ]);

            $paths = Dir::scan('spec/Fixture');

            foreach ($paths as $path) {
                $target = preg_replace('~^spec~', '', $path);
                if (is_dir($path)) {
                    expect(file_exists($this->tmpDir . $target))->toBe(true);
                } else {
                    expect(file_exists($this->tmpDir . $target . '.bak'))->toBe(true);
                }
            }

        });

        it("copies a directory recursively respecting the include option", function() {

            Dir::copy('spec/Fixture', $this->tmpDir, ['include' => '*.txt']);

            $files = Dir::scan($this->tmpDir, [
                'type' => 'file'
            ]);

            sort($files);
            expect($files)->toBe($this->normalize([
                $this->tmpDir . '/Fixture/Extensions/Childs/child1.txt',
                $this->tmpDir . '/Fixture/Nested/Childs/child1.txt',
                $this->tmpDir . '/Fixture/Nested/nested_file1.txt',
                $this->tmpDir . '/Fixture/Nested/nested_file2.txt',
                $this->tmpDir . '/Fixture/file1.txt'
            ]));

        });

        it("copies a directory recursively respecting the exclude option", function() {

            Dir::copy('spec/Fixture', $this->tmpDir, ['exclude' => '*.txt']);

            $files = Dir::scan($this->tmpDir, [
                'type' => 'file'
            ]);

            sort($files);
            expect($files)->toBe($this->normalize([
                $this->tmpDir . '/Fixture/Extensions/file.xml',
                $this->tmpDir . '/Fixture/Extensions/index.html',
                $this->tmpDir . '/Fixture/Extensions/index.php'
            ]));

        });

        it("copies a directory recursively but not following symlinks", function() {

            Dir::copy('spec/Fixture', $this->tmpDir, ['followSymlinks' => false]);

            $paths = Dir::scan('spec/Fixture');

            foreach ($paths as $path) {
                $target = preg_replace('~^spec~', '', $path);
                if ($target === $this->normalize('/Fixture/Extensions/Childs/child1.txt')) {
                    expect(file_exists($this->tmpDir . $target))->toBe(false);
                } else {
                    expect(file_exists($this->tmpDir . $target))->toBe(true);
                }
            }

        });

        it("throws an exception if the destination directory doesn't exists", function() {

            $closure = function() {
                Dir::copy('spec/Fixture', 'Unexisting/Folder');
            };

            expect($closure)->toThrow(new Exception("Unexisting destination path `Unexisting/Folder`."));

        });

    });

    describe("::remove()", function() {

        beforeEach(function() {
            $this->tmpDir = Dir::tempnam(sys_get_temp_dir(), 'spec');
        });

        afterEach(function() {
            Dir::remove($this->tmpDir);
        });

        it("removes a directory recursively", function() {

            Dir::copy('spec/Fixture', $this->tmpDir);

            $paths = Dir::scan('spec/Fixture');

            Dir::remove($this->tmpDir);

            foreach ($paths as $path) {
                $target = preg_replace('~^spec~', '', $path);
                expect(file_exists($this->tmpDir . $target))->toBe(false);
            }

            expect(file_exists($this->tmpDir))->toBe(false);

        });

        it("removes a directory recursively respecting the include option", function() {

            Dir::copy('spec/Fixture', $this->tmpDir);

            Dir::remove($this->tmpDir, ['include' => '*.txt']);

            $files = Dir::scan($this->tmpDir, [
                'type' => 'file'
            ]);

            sort($files);
            expect($files)->toBe($this->normalize([
                $this->tmpDir . '/Fixture/Extensions/file.xml',
                $this->tmpDir . '/Fixture/Extensions/index.html',
                $this->tmpDir . '/Fixture/Extensions/index.php'
            ]));


        });

        it("removes a directory recursively respecting the exclude option", function() {

            Dir::copy('spec/Fixture', $this->tmpDir);

            Dir::remove($this->tmpDir, ['exclude' => '*.txt']);

            $files = Dir::scan($this->tmpDir, [
                'type' => 'file'
            ]);

            sort($files);
            expect($files)->toBe($this->normalize([
                $this->tmpDir . '/Fixture/Extensions/Childs/child1.txt',
                $this->tmpDir . '/Fixture/Nested/Childs/child1.txt',
                $this->tmpDir . '/Fixture/Nested/nested_file1.txt',
                $this->tmpDir . '/Fixture/Nested/nested_file2.txt',
                $this->tmpDir . '/Fixture/file1.txt'
            ]));

        });

    });

    describe("::make()", function() {

        beforeEach(function() {
            $this->umask = umask(0);
            $this->tmpDir = Dir::tempnam(sys_get_temp_dir(), 'spec');
        });

        afterEach(function() {
            Dir::remove($this->tmpDir);
            umask($this->umask);
        });

        it("creates a nested directory", function() {

            $path = $this->tmpDir . '/My/Nested/Directory';
            $actual = Dir::make($path);
            expect($actual)->toBe(true);

            expect(file_exists($path))->toBe(true);

            $stat = stat($path);
            $mode = $stat['mode'] & 0777;
            expect($mode)->toBe(0755);

        });

        it("creates a nested directory with a specific mode", function() {

            $path = $this->tmpDir . '/My/Nested/Directory';
            $actual = Dir::make($path, ['mode' => 0777]);
            expect($actual)->toBe(true);

            expect(file_exists($path))->toBe(true);

            $stat = stat($path);
            $mode = $stat['mode'] & 0777;
            expect($mode)->toBe(0777);

        });

        it("creates multiple nested directories in a single call", function() {

            $paths = [
                $this->tmpDir . '/My/Nested/Directory',
                $this->tmpDir . '/Sub/Nested/Directory'
            ];
            $actual = Dir::make($paths);
            expect($actual)->toBe(true);

            foreach ($paths as $path) {
                expect(file_exists($path))->toBe(true);
            }

        });

    });

    describe("::tempnam()", function() {

        it("uses the system temp directory by default", function() {

            $dir = Dir::tempnam(null, 'spec');

            $temp = sys_get_temp_dir();

            expect($dir)->toMatch('~^' . $temp . '/spec~');

            Dir::remove($dir);

        });

    });

});
