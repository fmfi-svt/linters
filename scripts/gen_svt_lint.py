#!/usr/bin/python

# gen_svt_lint [-c|--correct|-p|--print] filename
# -c|--correct - correct the problems
# -p|--print - also print the lines where the problems occur

# return codes:
# 0 - OK
# 1 - error in usage
# 2 - problems in code detected and not corrected

import sys
import getopt
from problem_types import *
from os.path import basename
from subprocess import call

#problems
lineCorrectors = []
lineCorrectors.append(NonExpTabs())
lineCorrectors.append(TrailingWhsp())
fileCorrectors = []

#parse arguments
printProblems = False
corProblems = False
fileName = ""
helpString = basename(__file__) + ' [-c|--correct|-p|--print] <filename>'
try:
    opts, args = getopt.getopt(sys.argv[1:],"hcp",["correct","print"])
except getopt.GetoptError:
    print 'error in usage'
    print helpString
    sys.exit(1)

#process options
for opt, arg in opts:
    if opt == '-h':
        print helpString
        sys.exit()
    elif opt in ("-c", "--correct"):
        corProblems = True
    elif opt in ("-p", "--print"):
        printProblems = True

#process arguments
if (len(args) == 0):
    print helpString
    sys.exit(1)
fileName = args[0]

#open file and read it
try:
    fileHandle = open(fileName, 'r')
except IOError as e:
    print "Error encountered: %s" % str(e)
    sys.exit(1)
fileString = fileHandle.read()
fileHandle.close()

#detect and correct problems
problemFound = False
if (printProblems):
    problemList = []

#correct file problems
for fileCorrector in fileCorrectors:
    lineNumbers = fileCorrector.detectProblems(fileString)
    if (len(lineNumbers) != 0):
        problemFound = True
        if (printProblems):
            for lineNumber in lineNumbers:
                problemStr = makeProblemStr(fileCorrector.getCode(), fileCorrector.getDescription(), fileName, lineNumber)
                problemList.append(problemStr)
        if (corProblems):
            fileString = fileCorrector.correctProblems(fileString)

#correct line problems
lines = fileString.split("\n")
lineNumber = 0
for i in range(len(lines)):
    line = lines[i]

    for lineCorrector in lineCorrectors:
        problemDetected = lineCorrector.detectProblem(line)
        if (problemDetected):
            problemFound = True
            if (printProblems):
                problemStr = makeProblemStr(lineCorrector.getCode(), lineCorrector.getDescription(), fileName, i + 1)
                problemList.append(problemStr)
            if (corProblems):
                line = lineCorrector.correctProblem(line)

    if (corProblems):
        lines[i] = line

#update the file
if (corProblems and problemFound):
    fileHandle = open(fileName, 'w')
    for line in lines:
        fileHandle.write(line + "\n")
    fileHandle.close()

#list errors
if (problemFound and printProblems):
    for problem in problemList:
        print problem

#exit OK
if (problemFound):
    sys.exit(2)
sys.exit(0)
