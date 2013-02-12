<?php
/**
 * Runs php -l on linted files and outputs lint messages for encountered
 * syntax errors
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author     Frantisek Hajnovic <ferohajnovic@gmail.com>
 */

final class SvtPhpLintLinter extends ArcanistLinter {

    /**
     *
     * @var array[$path]->list($stdout, $stderr) output of running php -l
     * for file $path
     */
    private $results;

    public function willLintPaths(array $paths) {
        //create commands to be later executed in parallel
        $futures = array();
        foreach ($paths as $path) {
            $filepath = $this->getEngine()->getFilePathOnDisk($path);
            $futures[$path] = new ExecFuture('php -l %s', $filepath);
        }

        //execute up to 8 processes in parallel
        foreach (Futures($futures)->limit(8) as $path => $future) {
            try {
                $this->results[$path] = $future->resolvex();
            }
            catch (CommandException $e) {
                //255 is php -l exit code when syntax errors were found
                if ($e->getError() == 255) {
                    $this->results[$path][] = $e->getStdout();
                    $this->results[$path][] = $e->getStderr();
                }
                else {
                    throw $e;
                }
            }
        }

        return;
    }

    public function getLinterName() {
        return 'SvtPhpLintLinter';
    }

    public function lintPath($path) {
        list($stdout, $stderr) = $this->results[$path];

        //adjust output of php -l
        $stderr_lines = explode("\n", $stderr);
        $this->removeEmptyLines($stderr_lines);

        //create linter output
        $this->createLintMsgs($stderr_lines, $path);
    }

    protected function createLintMsgs(array $lines, $path) {
        foreach ($lines as $line) {
            $message = new ArcanistLintMessage();
            $message->setPath($path);
            $message->setName($this->getLinterName());
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

            $matches = array();
            $match_res = preg_match("/^(.*) on line ([0-9]{1,})$/",
                    $line, $matches);

            //php -l output line in the format "error-msg on line line-number"
            if ($match_res == 1) {
                $message->setLine($matches[2]);
                $message->setCode("PHP-LINT");
                $message->setDescription($matches[1]);
            }
            //unexpected php -l output line
            else {
                $message->setCode("PHP-LINT-UNEXP");
                $message->setDescription($line);
            }

            $this->addLintMessage($message);
        }
    }

    protected function removeEmptyLines(array &$lines) {
        $new_lines = array();
        foreach ($lines as $line) {
            if (preg_match("/^$/", $line) == 1) {
                continue;
            }
            $new_lines[] = $line;
        }

        $lines = $new_lines;
        return $lines;
    }
}
