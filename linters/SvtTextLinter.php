<?php

/**
 * Minor differences to ArcanistTextLinter
 * http://www.phabricator.com/docs/arcanist/class/ArcanistTextLinter.html
 *
 * @copyright Copyright (c) 2013 The FMFI Anketa authors (see AUTHORS).
 * Use of this source code is governed by a license that can be
 * found in the LICENSE file in the project root directory.
 *
 * @author         Frantisek Hajnovic <ferohajnovic@gmail.com>
 */
final class SvtTextLinter extends ArcanistLinter {

    const LINT_DOS_NEWLINE              = 1;
    const LINT_TAB                      = 2;
    const LINT_LINE_WRAP                = 3;
    const LINT_EOF_NEWLINE              = 4;
    const LINT_TRAILING_WHITESPACE      = 5;
    const LINT_NO_COMMIT                = 6;

    private $maxLineLength = 80;

    public function willLintPaths(array $paths) {
        $this->configureLinter();
        return;
    }

    protected function configureLinter() {
        $working_copy = $this->getEngine()->getWorkingCopy();

        $maxLineLength = $working_copy->getConfig('lint.text.maxlinelength');
        if ($maxLineLength !== null && $maxLineLength > 0) {
            $this->maxLineLength =  $maxLineLength;
        }
    }

    public function getLinterName() {
        return 'SvtTextLinter';
    }

    public function getLintSeverityMap() {
        //default is ERROR
        return array(
            self::LINT_LINE_WRAP => ArcanistLintSeverity::SEVERITY_WARNING,
            self::LINT_TRAILING_WHITESPACE =>
                ArcanistLintSeverity::SEVERITY_AUTOFIX,
        );
    }

    public function getLintNameMap() {
        return array(
            self::LINT_DOS_NEWLINE         => 'DOS Newlines',
            self::LINT_TAB                 => 'Tab Literal',
            self::LINT_LINE_WRAP           => 'Line Too Long',
            self::LINT_EOF_NEWLINE         => 'File Does Not End in Newline',
            self::LINT_TRAILING_WHITESPACE => 'Trailing Whitespace',
            self::LINT_NO_COMMIT           => 'Explicit @no'.'commit',
        );
    }

    public function lintPath($path) {
        if (!strlen($this->getData($path))) {
            // If the file is empty, don't bother; particularly, don't require
            // the user to add a newline.
            return;
        }

        $this->lintDosNewline($path);
        $this->lintTab($path);

        if ($this->didStopAllLinters()) {
            return;
        }

        $this->lintLineWrap($path);
        $this->lintEofNewline($path);
        $this->lintTrailingWhitespace($path);

        if ($this->getEngine()->getCommitHookMode()) {
            $this->lintNoCommit($path);
        }
    }

    protected function lintDosNewline($path) {
        $pos = strpos($this->getData($path), "\r");
        if ($pos !== false) {
            $this->raiseLintAtOffset(
                $pos,
                self::LINT_DOS_NEWLINE,
                'You must use ONLY Unix linebreaks ("\n") in source code.',
                "\r");
            if ($this->isMessageEnabled(self::LINT_DOS_NEWLINE)) {
                $this->stopAllLinters();
            }
        }
    }

    protected function lintTab($path) {
        $data = $this->getData($path);
        $lines = explode("\n", $data);

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            //detect non-expanded tabs
            $preg = preg_match(
                '/\t/',
                $line);
            if (!$preg) {
                continue;
            }

            //expand tabs
            $exptab_line = str_replace("\t", "    ", $line);

            //there might be new trailing whitespaces
            $rmtrail_line = preg_replace("/ +$/", '', $exptab_line);

            $this->raiseLintAtLine(
                $i + 1,
                1,
                self::LINT_TAB,
                'This line contains tab literal. Consider setting up your '.
                    'editor to use spaces for indentation',
                $line,
                $rmtrail_line);
        }
    }

    protected function lintLineWrap($path) {
        $lines = explode("\n", $this->getData($path));

        $width = $this->maxLineLength;
        foreach ($lines as $line_idx => $line) {
            if (strlen($line) > $width) {
                $this->raiseLintAtLine(
                    $line_idx + 1,
                    1,
                    self::LINT_LINE_WRAP,
                    'This line is '.number_format(strlen($line)).
                        ' characters long, but the convention is '.$width.
                        ' characters.',
                    $line);
            }
        }
    }

    protected function lintEofNewline($path) {
        $data = $this->getData($path);
        if (!strlen($data) || $data[strlen($data) - 1] != "\n") {
            $this->raiseLintAtOffset(
                strlen($data),
                self::LINT_EOF_NEWLINE,
                "Files must end in a newline.",
                '',
                "\n");
        }
    }

    protected function lintTrailingWhitespace($path) {
        $data = $this->getData($path);

        $matches = null;
        $preg = preg_match_all(
            '/ +$/m',
            $data,
            $matches,
            PREG_OFFSET_CAPTURE);

        if (!$preg) {
            return;
        }

        foreach ($matches[0] as $match) {
            list($string, $offset) = $match;
            $this->raiseLintAtOffset(
                $offset,
                self::LINT_TRAILING_WHITESPACE,
                'This line contains trailing whitespace. Consider setting '.
                    'up your editor to automatically remove trailing '.
                    'whitespace, you will save time.',
                $string,
                '');
        }
    }

    private function lintNoCommit($path) {
        $data = $this->getData($path);

        $deadly = '@no'.'commit';

        $offset = strpos($data, $deadly);
        if ($offset !== false) {
            $this->raiseLintAtOffset(
                $offset,
                self::LINT_NO_COMMIT,
                'This file is explicitly marked as "'.$deadly.
                    '", which blocks commits.',
                $deadly);
        }
    }

}
