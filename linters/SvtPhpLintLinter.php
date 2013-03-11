<?php
/**
 * Checks php files for warnings and errors. Most notably, runs php -l
 * on linted files and outputs lint messages for encountered syntax errors
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author     Frantisek Hajnovic <ferohajnovic@gmail.com>
 */

final class SvtPhpLintLinter extends ArcanistLinter {

    const LINT_PHP_ERRS                 = 1;
    const LINT_VAR_DUMPS                = 2;

    /**
     *
     * @var array[$path]->list($stdout, $stderr) output of running php -l
     * for file $path
     */
    private $results;

    private $VARDUMP_FUNCS = array("var_dump", "var_export", "print_r", "echo");

    public function getLintSeverityMap() {
        //default is ERROR
        return array(
            self::LINT_VAR_DUMPS => ArcanistLintSeverity::SEVERITY_WARNING,
        );
    }

    public function getLintNameMap() {
        return array(
            self::LINT_PHP_ERRS         => 'PHP Error',
            self::LINT_VAR_DUMPS        => 'Var dumped',
        );
    }

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
        $this->lintVarDumps($path);
        $this->lintPhpErrs($path);
    }

    protected function lintVarDumps($path) {
        $data = $this->getData($path);

        //make regular expression to search for
        $bad_re = '';
        $bad_str = '';
        foreach ($this->VARDUMP_FUNCS as $bad_func) {
            $bad_re .= '(' . $bad_func . ')|';
            $bad_str .= '\'' . $bad_func . '\', ';
        }
        $bad_re = substr($bad_re, 0, -1);
        $bad_re .= '';
        $bad_str = substr($bad_str, 0, -2);

        //get matches to our RE and locations of the matches
        $matches = null;
        $preg = preg_match_all(
            "/{$bad_re}(.*{$bad_re})?/",
            $data,
            $matches,
            PREG_OFFSET_CAPTURE);

        if (!$preg) {
            return;
        }

        //raise lints for all matches
        foreach ($matches[0] as $match) {
            list($string, $offset) = $match;
            $this->raiseLintAtOffset(
                $offset,
                self::LINT_VAR_DUMPS,
                'Php functions ' . $bad_str . ' should not be used as twig ' .
                    'engine should be enough to do the output',
                $string);
        }
    }

    protected function lintPhpErrs($path) {
        list($stdout, $stderr) = $this->results[$path];

        //adjust output of php -l
        $stderr_lines = explode("\n", $stderr);
        $this->removeEmptyLines($stderr_lines);

        foreach ($stderr_lines as $line) {
            $matches = array();
            $match_res = preg_match("/^(.*) on line ([0-9]{1,})$/",
                    $line, $matches);

            //php -l output line in the format "error-msg on line line-number"
            if ($match_res == 1) {
                $this->raiseLintAtLine(
                        $matches[2],
                        1,
                        self::LINT_PHP_ERRS,
                        $matches[1]);
            }
            //unexpected php -l output line
            else {
                $message = new ArcanistLintMessage();
                $message->setPath($path);
                $message->setName($this->getLinterName());
                $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

                $message->setCode("PHP-LINT-UNEXP");
                $message->setDescription($line);

                $this->addLintMessage($message);
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
