# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/pdf_word_count/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/pdf_word_count/submissions"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    try:
        data_path = os.path.join(test.testcase_path, "data")
        if os.path.isdir(data_path):
            shutil.rmtree(data_path)
        os.mkdir(data_path)
    except OSError:
        pass

    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])


############################################################################


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*pdf")))
    subprocess.call(["rm"] + ["-rf"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))


@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    try:
        test.validate_complete_config(config_path)
    except Exception:
        traceback.print_exc()
        raise

@testcase
def too_few(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "words_249.pdf"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    subprocess.call(["rm","-f",os.path.join(test.testcase_path, "data", "test01_words_249.pdf")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","too_few_grade.txt","-b")
    test.json_diff("results.json","too_few_results.json")
    test.empty_file("test02/STDOUT_0.txt")
    test.empty_file("test02/STDERR_0.txt")
    test.diff("test02/STDOUT_1.txt","too_few_test02_STDOUT_1.txt")
    test.empty_file("test02/STDERR_1.txt")

@testcase
def too_many(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "words_1463.pdf"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    subprocess.call(["rm","-f",os.path.join(test.testcase_path, "data", "test01_words_1463.pdf")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","too_many_grade.txt","-b")
    test.json_diff("results.json","too_many_results.json")
    test.empty_file("test02/STDOUT_0.txt")
    test.empty_file("test02/STDERR_0.txt")
    test.diff("test02/STDOUT_1.txt","too_many_test02_STDOUT_1.txt")
    test.empty_file("test02/STDERR_1.txt")

@testcase
def just_right(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "words_881.pdf"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    subprocess.call(["rm","-f",os.path.join(test.testcase_path, "data", "test01/words_881.pdf")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","just_right_grade.txt","-b")
    test.json_diff("results.json","just_right_results.json")
    test.empty_file("test02/STDOUT_0.txt")
    test.empty_file("test02/STDERR_0.txt")
    test.diff("test02/STDOUT_1.txt","just_right_test02_STDOUT_1.txt")
    test.empty_file("test02/STDERR_1.txt")



