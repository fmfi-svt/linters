<?php
/**
 * Outputs warning for each usage of var_dump, print, echo and
 * similar functions/language constructs
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author     Frantisek Hajnovic <ferohajnovic@gmail.com>
 */

final class SvtPhpVarDumpsLinter extends ArcanistLinter {

    const LINT_VAR_DUMPS = 1;

    public function getLintSeverityMap() {
        return array(
            self::LINT_VAR_DUMPS => ArcanistLintSeverity::SEVERITY_WARNING,
        );
    }

    public function getLintNameMap() {
        return array(
            self::LINT_VAR_DUMPS => 'Var dump usage',
        );
    }

    public function willLintPaths(array $paths) {
        return;
    }

    public function getLinterName() {
        return 'SvtPhpVarDumpLinter';
    }

    public function lintPath($path) {
        $this->lintVarDumps($path);
    }

    protected function lintVarDumps($path) {
        $data = $this->getData($path);

        //compose the RE to look for
        $bad_funcs_re = '\b(var_dump|var_export|print_r|echo|print)\s*\(';
        $bad_cons_re = '\b(echo|print)\b';
        $bad_re = '/(' . $bad_funcs_re . ')|(' . $bad_cons_re . ')/';

        //get matches to our RE and locations of the matches
        $matches = null;
        $preg = preg_match_all($bad_re, $data, $matches, PREG_OFFSET_CAPTURE);

        if (!$preg) {
            return;
        }

        //raise lints for all matches
        foreach ($matches[0] as $match) {
            list($string, $offset) = $match;
            $this->raiseLintAtOffset(
                $offset,
                self::LINT_VAR_DUMPS,
                'This function or language construct ' .
                    'should not be used since twig engine does the output',
                $string);
        }
    }

}
