<?php
/**
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 */

final class SvtLintEngine extends ArcanistLintEngine {
    public function buildLinters() {
        //create linters
        $pylint_linter = new ArcanistPyLintLinter();
        $phpcs_linter = new ArcanistPhpcsLinter();
        $gen_svt_linter = new GeneralSvtLinter();
        $phplint_svt_linter = new PhpLintSvtLinter();

        $paths = $this->getPaths();
        $paths_to_bck = array();

        //unset non-existent paths
        foreach ($paths as $key => $path) {
            if (!$this->pathExists($path)) {
                unset($paths[$key]);
            }
        }

        //set which files will be linted by which linter
        foreach ($paths as $path) {
            if (preg_match('/\.py$/', $path)) {
                $pylint_linter->addPath($path);
            }
            if (preg_match('/\.php$/', $path)) {
                $phpcs_linter->addPath($path);
                $phplint_svt_linter->addPath($path);
            }
            if (preg_match('/\.php$|\.py$|\.js$|\.css$|\.twig$|\.yml$/', $path)) {
                $gen_svt_linter->addPath($path);
                array_push($paths_to_bck, $path);
            }
        }

        //make back-ups of files which might be automatically corrected
        $backups_ok = $this->backupFiles($paths_to_bck);
        $gen_svt_linter->canCorrect($backups_ok);

        return array(
            //$pylint_linter,
            //$phpcs_linter,
            $phplint_svt_linter,
            $gen_svt_linter,
        );
    }

    private function backupFiles(array $paths) {
        $output = array();
        $return = 0;

        //clear backup directory
        exec("rm -rf " . AbstractSvtLinter::LINT_FOLDER . "linters/bck/*.bck", $output, $return);
        if ($return != 0) {
            echo "Clearing the backup directory failed\n";
            return false;
        }

        //backups all the files
        foreach ($paths as $path) {
            $path_on_disk = $this->getFilePathOnDisk($path);
            $basename = basename($path_on_disk);
            exec("cp $path_on_disk " . AbstractSvtLinter::LINT_FOLDER .
                    "linters/bck/$basename.bck", $output, $return);
            if ($return != 0) {
                echo "Backup failed\n";
                return false;
            }
        }

        return true;
    }
}



