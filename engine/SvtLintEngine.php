<?php
/**
 * Lint engine - glue code that specifies which linter will be run
 * on which file
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author     Frantisek Hajnovic <ferohajnovic@gmail.com>
 */

final class SvtLintEngine extends ArcanistLintEngine {

    public function buildLinters() {
        //create linters
        $svt_text_linter = new SvtTextLinter();
        $svt_php_syntax_linter = new SvtPhpSyntaxLinter();
        $svt_php_var_dumps_linter = new SvtPhpVarDumpsLinter();

        //get paths of files to be checked
        $paths = $this->getPaths();
        foreach ($paths as $key => $path) {
            if (!$this->pathExists($path)) {
                unset($paths[$key]);
            }
        }

        //set which files will be linted by which linter
        foreach ($paths as $path) {
            if (preg_match('/\.php$/', $path)) {
                $svt_php_syntax_linter->addPath($path);
                $svt_php_var_dumps_linter->addPath($path);
            }
            if (preg_match('/\.php$|\.py$|\.js$|\.css$|\.twig$|\.yml$/',
                    $path)) {
                $svt_text_linter->addPath($path);
            }
        }

        //return linters and lint
        return array(
            $svt_php_syntax_linter,
            $svt_php_var_dumps_linter,
            $svt_text_linter,
        );
    }

}
