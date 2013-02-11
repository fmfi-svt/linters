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
        $svt_phplint_linter = new SvtPhpLintLinter();

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
                $svt_phplint_linter->addPath($path);
            }
            if (preg_match('/\.php$|\.py$|\.js$|\.css$|\.twig$|\.yml$/',
                    $path)) {
                $svt_text_linter->addPath($path);
            }
        }

        //return linters and lint
        return array(
            $svt_phplint_linter,
            $svt_text_linter,
        );
    }
}
