#!/usr/bin/python

import re

def makeProblemStr(code, desc, fname, ln):
    codeStr = "[" + code + "]"
    descStr = "[" + desc + "]"
    fnameLnStr = "[%s:%d]" % (fname, ln)
    return "%s %s %s" % (codeStr.ljust(10), descStr.ljust(35), fnameLnStr)

#line correctors-----------------------------------------
class LineCorrector:
    """Find (and correct) problems with code on one line"""
    def detectProblem(self, line):
        """Return if a problem was found in the line string"""
        raise NotImplementedError

    def correctProblem(self, line):
        """Return a corrected line string"""
        raise NotImplementedError

    def getDescription(self):
        raise NotImplementedError

    def getCode(self):
        raise NotImplementedError

#trailing whitespaces
class TrailingWhsp(LineCorrector):
    def detectProblem(self, line):
        return (re.search(r"\ $", line) != None)

    def correctProblem(self, line):
        return line.rstrip()

    def getDescription(self):
        return "Trailing whitespace"

    def getCode(self):
        return "L-WHSP"

#non-expanded tabs
class NonExpTabs(LineCorrector):
    def detectProblem(self, line):
        return (re.search(r"\t", line) != None)

    def correctProblem(self, line):
        return line.expandtabs(4)

    def getDescription(self):
        return "Non-expanded tabs"

    def getCode(self):
        return "L-NONEXP"

#file correctors-----------------------------------------
class FileCorrector:
    """Find (and correct) problems with code exceeding one line"""
    def detectProblems(self, fileString):
        """Return line numbers where the problem was found"""
        raise NotImplementedError

    def correctProblems(self, fileString):
        """Return the corrected file's string"""
        raise NotImplementedError

    def getDescription(self):
        raise NotImplementedError

    def getCode(self):
        raise NotImplementedError
