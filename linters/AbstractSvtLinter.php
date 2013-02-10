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

abstract class AbstractSvtLinter extends ArcanistLinter {
    /********************************************************/
    /* Constants
    /********************************************************/

    const LINT_FOLDER = './lint/';

    const C_ALL_OK = 0;
    const C_SCRIPT_ERR = 1;
    const C_PROB_DET = 2;

    const ARG_ONLY_PRINT = '-p';
    const ARG_ALSO_COR = '-pc';

    /********************************************************/
    /* Data
    /********************************************************/

    protected $backups_ok = true;
    protected $global_confirm = false;
    protected $can_correct = false;

    /********************************************************/
    /* Interface
    /********************************************************/

    /**
     * sets if the linter may automatically do corrections
     */
    public function canCorrect($can_correct) {
        $this->can_correct = $can_correct;
    }

    public function willLintPaths(array $paths) {
        //global confirmation
        $this->globalConfirmation($paths);

        return;
    }

    public function getLintSeverityMap() {
        return array();
    }

    public function getLintNameMap() {
        return array();
    }

    public function lintPath($path) {
        $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

        //get options
        $print_opt = $this->getPrintOpt();
        $correct_opt = $this->getCorrectOpt();
        $perfile_confirm_opt = $this->getPerFileConfirmOpt();
        $global_confirm_opt = $this->getGlobalConfirmOpt();

        $output = array();
        $return = 0;

        //get initial errors
        $had_problems = false;
        $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ONLY_PRINT, $path_on_disk,
                $output, $return);
        if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
            $this->lintError($path_on_disk, "Error running external script");
            return;
        }
        if ($func_ret == AbstractSvtLinter::C_PROB_DET) {
            $had_problems = true;
        }

        //per-file confirmation
        if ($perfile_confirm_opt) {
            if ($correct_opt) {
                $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ONLY_PRINT, $path_on_disk,
                        $output, $return);
                if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
                    $this->lintError($path_on_disk, "Error running external script");
                    return;
                }
                if ($print_opt) {
                    $this->outputProblems($output);
                }
                if ($func_ret == AbstractSvtLinter::C_PROB_DET) {
                    $confirmed = $this->askConfirmation(
                            "File $path contains problems. Do you want to correct_opt them automatically?");
                    if ($confirmed) {
                        $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ALSO_COR,
                                $path_on_disk, $output, $return);
                        if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
                            $this->lintError($path_on_disk, "Error running external script");
                            return;
                        }
                    }
                }
            }
        }
        //global confirmation
        else if ($global_confirm_opt) {
            if ($correct_opt && $this->global_confirm) {
                $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ALSO_COR, $path_on_disk,
                        $output, $return);
                if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
                    $this->lintError($path_on_disk, "Error running external script");
                    return;
                }
            }
        }
        //no confirmation
        else {
            if ($correct_opt) {
                $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ALSO_COR, $path_on_disk,
                        $output, $return);
                if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
                    $this->lintError($path_on_disk, "Error running external script");
                    return;
                }
                if ($print_opt) {
                    $this->outputProblems($output);
                }
            }
        }

        //get remaining (uncorrected) problems
        $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ONLY_PRINT, $path_on_disk,
                $output, $return);
        if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
            $this->lintError($path_on_disk, "Error running external script");
            return;
        }

        //compose linter output
        $this->createLintMsgs($output, $path);

        //if there were initially errors, report it
        if ($had_problems) {
            $message = new ArcanistLintMessage();
            $message->setPath($path);
            $message->setCode("SVT-LINTER");
            $message->setName($this->getLinterName());
            $message->setDescription("There were problems detected in the code. They might have been " .
                    "corrected (if you do not see other warnings except for this one), but the ".
                    "corrections will be included only in the next commit.");
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            $this->addLintMessage($message);
        }
    }

    /********************************************************/
    /* Implementation
    /********************************************************/

    /**
     * @return boolean should the problems in the code be printed before asking for auto-correction
     * confirmation
     */
    protected function getPrintOpt() {
        $working_copy = $this->getEngine()->getWorkingCopy();

        $print = $working_copy->getConfig('svtlint.print');

        return $print;
    }

    /**
     * @return boolean should the linter automatically try to correct the problems in the code
     */
    protected function getCorrectOpt() {
        $working_copy = $this->getEngine()->getWorkingCopy();

        $correct = $working_copy->getConfig('svtlint.correct');

        return ($correct && $this->can_correct);
    }

    /**
     * @return boolean should the linter ask for one confirmation to try automatically correct all
     * the problems
     */
    protected function getGlobalConfirmOpt() {
        $working_copy = $this->getEngine()->getWorkingCopy();

        $confirm = $working_copy->getConfig('svtlint.confirm');
        $perfile = $working_copy->getConfig('svtlint.perfile');

        return ($confirm && !$perfile);
    }

    /**
     * @return boolean should the linter ask for confirmation for each file to try automatically correct
     * the problems in that file
     */
    protected function getPerFileConfirmOpt() {
        $working_copy = $this->getEngine()->getWorkingCopy();

        $confirm = $working_copy->getConfig('svtlint.confirm');
        $perfile = $working_copy->getConfig('svtlint.perfile');

        return ($confirm && $perfile);
    }

    /**
     * in case the automatical correction is ON and
     */
    protected function globalConfirmation(array $paths) {
        //get options
        $global_confirm_opt = $this->getGlobalConfirmOpt();
        $correct_opt = $this->getCorrectOpt();
        $print_opt = $this->getPrintOpt();

        //find all the problems
        if ($correct_opt && $global_confirm_opt) {
            $probl_detected = false;
            foreach ($paths as $path) {
                $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);
                $output = array();
                $return = 0;
                $func_ret = $this->runSvtLintScript(AbstractSvtLinter::ARG_ONLY_PRINT,
                        $path_on_disk, $output, $return);
                if ($func_ret == AbstractSvtLinter::C_SCRIPT_ERR) {
                    $this->lintError($path_on_disk, "Error running external script");
                    return AbstractSvtLinter::C_SCRIPT_ERR;
                }
                if ($func_ret == AbstractSvtLinter::C_PROB_DET) {
                    $probl_detected = true;
                    if ($print_opt) {
                        $this->outputProblems($output);
                    }
                }
            }

            //ask for a confirmation to correct all detected problems
            if ($probl_detected) {
                $this->global_confirm = $this->askConfirmation(
                        "Do you want the linter to try correct the problems above automatically?");
            }
        }
    }

    /**
     * @return int should return 0 if no problems detected, 1 if there was a error in execution
     * of the script, 2 if script detected problems with the code
     *
     * @param string $args arguments for the external script
     * @param string $file_path path of the file on which we call the external script
     * @param array $output output of the called script
     * @param int $return exit code of the called script
     */
    abstract protected function runSvtLintScript($args, $file_path, &$output, &$return);

    abstract protected function createLintMsgs(array $script_output, $path);

    protected function askConfirmation($question) {
        while (true) {
            echo "$question [Y|n]:";
            $answer = trim(fgets(STDIN));
            if ($answer == "y" || $answer == "Y" || $answer == "") {
                return true;
            }
            else if ($answer == "N" || $answer == "n") {
                return false;
            }
        }
    }

    protected function arrayToString(array $array) {
        $string = "";
        foreach ($array as $item) {
            $string .= $item . "\n";
        }
        return $string;
    }

    protected function outputProblems(array $problemStrings) {
        $problemsString = $this->arrayToString($problemStrings);
        echo $problemsString;
    }

    protected function lintError($path, $msg) {
        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setName($this->getLinterName());
        $message->setDescription($msg);
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
        $this->addLintMessage($message);
    }
}
