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

final class GeneralSvtLinter extends AbstractSvtLinter {
    public function getLinterName() {
        return 'GeneralSvtLinter';
    }

    protected function runSvtLintScript($args, $file_path, &$output, &$return) {
        $output = array();
        $return = 0;
        exec("python " . AbstractSvtLinter::LINT_FOLDER . "scripts/gen_svt_lint.py " .
                    $args . " " . $file_path, $output, $return);

        return $return;
    }

    protected function createLintMsgs(array $script_output, $path) {
        foreach ($script_output as $line) {
            $matches = array();
            preg_match("/\[(.{1,})\]\ *\[(.{1,})\].*\:([0-9]{1,})/", $line, $matches);
            $message = new ArcanistLintMessage();
            $message->setPath($path);
            $message->setLine($matches[3]);
            $message->setCode($matches[1]);
            $message->setName($this->getLinterName());
            $message->setDescription($matches[2]);
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            $this->addLintMessage($message);
        }
    }
}
