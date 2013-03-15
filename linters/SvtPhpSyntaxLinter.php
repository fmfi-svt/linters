<?php
/**
 * Runs php -l on linted files and outputs error messages for
 * encountered syntax errors
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author     Frantisek Hajnovic <ferohajnovic@gmail.com>
 */

final class SvtPhpSyntaxLinter extends ArcanistLinter {

    const LINT_SYNTAX = 1;

    /**
     *
     * @var array[$path]->list($stdout, $stderr) output of running php -l
     * for file $path
     */
    private $phpLintOutputs;

    public function getLintSeverityMap() {
        return array(
            self::LINT_SYNTAX => ArcanistLintSeverity::SEVERITY_ERROR,
        );
    }

    public function getLintNameMap() {
        return array(
            self::LINT_SYNTAX => 'PHP syntax error',
        );
    }

    public function willLintPaths(array $paths) {
        $this->getPhpLintOutputs($paths);

        return;
    }

    public function getLinterName() {
        return 'SvtPhpSyntaxLinter';
    }

    public function lintPath($path) {
        $this->lintSyntax($path);
    }

    protected function lintSyntax($path) {
        list($stdout, $stderr) = $this->phpLintOutputs[$path];

        //adjust output of php -l
        $stderr_lines = explode("\n", $stderr);
        $this->removeEmptyLines($stderr_lines);

        foreach ($stderr_lines as $line) {
            $matches = array();
            $match_res = preg_match(
                    "/^(.*) on line ([0-9]{1,})$/",
                    $line,
                    $matches);

            //php -l output line in the format "error-msg on line line-number"
            if ($match_res == 1) {
                $this->raiseLintAtLine(
                        $matches[2],
                        1,
                        self::LINT_SYNTAX,
                        $matches[1]);
            }
            //unexpected php -l output line
            else {
                $this->raiseLintAtPath(
                        self::LINT_SYNTAX,
                        "Unexpected php -l output line: " . $line);
            }
        }
    }

    protected function getPhpLintOutputs(array $paths) {
        //create commands "php -l" to be later executed in parallel
        $futures = array();
        foreach ($paths as $path) {
            $filepath = $this->getEngine()->getFilePathOnDisk($path);
            $futures[$path] = new ExecFuture('php -l %s', $filepath);
        }

        //execute up to 8 processes in parallel
        foreach (Futures($futures)->limit(8) as $path => $future) {
            try {
                $this->phpLintOutputs[$path] = $future->resolvex();
            }
            catch (CommandException $e) {
                //255 is php -l exit code when syntax errors were found
                if ($e->getError() == 255) {
                    $this->phpLintOutputs[$path][] = $e->getStdout();
                    $this->phpLintOutputs[$path][] = $e->getStderr();
                }
                else {
                    throw $e;
                }
            }
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
