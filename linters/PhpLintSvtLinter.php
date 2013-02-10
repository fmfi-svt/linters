<?php
/**
 *
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author     Frantisek Hajnovic <ferohajnovic@gmail.com>
 */

final class PhpLintSvtLinter extends AbstractSvtLinter {
    public function getLinterName() {
        return 'PhpLintSvtLinter';
    }

    public function lintPath($path) {
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        $output = array();
        $return = 0;

        //get problems
        $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ONLY_PRINT,
                $path_on_disk, $output, $return);
        if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
            $this->lintError($path_on_disk, "Error running external script");
            return;
        }

        //compose linter output
        $this->createLintMsgs($output, $path);
    }

    protected function runSvtLintScript($args, $file_path, &$output, &$return) {
        $output = array();
        $return = 0;
        exec("php -l " . $file_path .' 2>&1', $output, $return);

        if ($return != 0) {
            return AbstractSvtLinter::C_PROB_DET;
        }

        return AbstractSvtLinter::C_ALL_OK;
    }

    protected function createLintMsgs(array $script_output, $path) {
        //No syntax errors detected
        if (count($script_output) == 1) {
            return;
        }

        //parse output of the php -l
        foreach ($script_output as $line) {
            if (preg_match("/^Errors parsing.*/", $line) == 1) {
                continue;
            }
            $matches = array();
            preg_match("/(.*) on line ([0-9]{1,})/", $line, $matches);
            if (count($matches) != 3) {
                $message = new ArcanistLintMessage();
                $message->setPath($path);
                $message->setCode("PHP-LINT");
                $message->setName($this->getLinterName());
                $message->setDescription($line);
                $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                $this->addLintMessage($message);
            }
            else {
                $message = new ArcanistLintMessage();
                $message->setPath($path);
                $message->setCode("PHP-LINT");
                $message->setLine($matches[2]);
                $message->setName($this->getLinterName());
                $message->setDescription($matches[1]);
                $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
                $this->addLintMessage($message);
            }
        }
    }
}
