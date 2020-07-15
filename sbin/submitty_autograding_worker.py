#!/usr/bin/env python3

import os
import time
import signal
import shutil
import json
from submitty_utils import dateutils
import multiprocessing
import contextlib
import traceback
import zipfile
from pathlib import Path

from autograder import autograding_utils
from autograder import grade_item

# ==================================================================================
CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
NUM_GRADING_SCHEDULER_WORKERS_string = OPEN_JSON['num_grading_scheduler_workers']
NUM_GRADING_SCHEDULER_WORKERS_int = int(NUM_GRADING_SCHEDULER_WORKERS_string)
DAEMON_UID = OPEN_JSON['daemon_uid']

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']
AUTOGRADING_LOG_PATH = OPEN_JSON['autograding_log_path']
AUTOGRADING_STACKTRACE_PATH = os.path.join(OPEN_JSON['site_log_path'], 'autograding_stack_traces')

JOB_ID = '~WORK~'

ALL_WORKERS_JSON = os.path.join(SUBMITTY_DATA_DIR, "autograding_TODO", "autograding_worker.json")


# ==================================================================================
# ==================================================================================
def worker_process(which_machine, address, which_untrusted, my_server):

    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        autograding_utils.log_message(
            AUTOGRADING_LOG_PATH, JOB_ID,
            message="ERROR: must be run by DAEMON_USER"
        )
        raise SystemExit(
            "ERROR: the submitty_autograding_worker.py script must be run by the DAEMON_USER"
        )

    # ignore keyboard interrupts in the worker processes
    signal.signal(signal.SIGINT, signal.SIG_IGN)
    counter = 0

    # The full name of this worker
    worker_name = f"{my_server}_{address}_{which_untrusted}"

    # Set up key autograding_DONE directories
    done_dir = os.path.join(SUBMITTY_DATA_DIR, "autograding_DONE")
    done_queue_file = os.path.join(done_dir, f"{worker_name}_queue.json")
    results_zip = os.path.join(done_dir, f"{worker_name}_results.zip")

    # Set up key autograding_TODO directories
    todo_dir = os.path.join(SUBMITTY_DATA_DIR, "autograding_TODO")
    autograding_zip = os.path.join(todo_dir, f"{worker_name}_autograding.zip")
    submission_zip = os.path.join(todo_dir, f"{worker_name}_submission.zip")
    todo_queue_file = os.path.join(todo_dir, f"{worker_name}_queue.json")

    # Establish the the directory in which we will do our work
    working_directory = os.path.join(
        SUBMITTY_DATA_DIR,
        'autograding_tmp',
        which_untrusted,
        "tmp"
    )

    while True:
        if os.path.exists(todo_queue_file):
            try:
                # Attempt to grade the submission. Get back the location of the results.
                results_zip_tmp = grade_item.grade_from_zip(
                    working_directory,
                    which_untrusted,
                    autograding_zip,
                    submission_zip
                )
                shutil.copyfile(results_zip_tmp, results_zip)
                os.remove(results_zip_tmp)
                # At this point, we will assume that grading has progressed successfully enough to
                # return a coherent answer, and will say as much in the done queue file
                response = {
                        'status': 'success',
                        'message': 'Grading completed successfully'
                    }
            except Exception:
                # If we threw an error while grading, log it.
                autograding_utils.log_message(
                    AUTOGRADING_LOG_PATH, JOB_ID,
                    message=f"ERROR attempting to unzip graded item: {which_machine} "
                            f"{which_untrusted}. for more details, see traces entry."
                )
                autograding_utils.log_stack_trace(
                    AUTOGRADING_STACKTRACE_PATH, JOB_ID,
                    trace=traceback.format_exc()
                )
                # TODO: It is possible that autograding failed after multiple steps.
                # In this case, we may be able to salvage a portion of the autograding_results
                # directory.

                # Because we failed grading, we will respond with an empty results zip.
                results_zip_tmp = zipfile.ZipFile(results_zip, 'w')
                results_zip_tmp.close()

                # We will also respond with a done_queue_file which contains a failure message.
                response = {
                    'status': 'fail',
                    'message': traceback.format_exc()
                }
            finally:
                # Regardless of if we succeeded or failed, create a done queue file to
                # send to the shipper.
                with open(todo_queue_file, 'r') as infile:
                    queue_obj = json.load(infile)
                    queue_obj["done_time"] = dateutils.write_submitty_date(milliseconds=True)
                    queue_obj['autograding_status'] = response
                with open(done_queue_file, 'w') as outfile:
                    json.dump(queue_obj, outfile, sort_keys=True, indent=4)
                # Clean up temporary files.
                with contextlib.suppress(FileNotFoundError):
                    os.remove(autograding_zip)
                with contextlib.suppress(FileNotFoundError):
                    os.remove(submission_zip)
                with contextlib.suppress(FileNotFoundError):
                    os.remove(todo_queue_file)
            counter = 0
        else:
            if counter >= 10:
                print(which_machine, which_untrusted, "wait")
                counter = 0
            counter += 1
            time.sleep(1)


# ==================================================================================
# ==================================================================================
def launch_workers(my_name, my_stats):
    num_workers = my_stats['num_autograding_workers']

    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        raise SystemExit(
            "ERROR: the submitty_autograding_worker.py script must be run by the DAEMON_USER"
        )

    autograding_utils.log_message(
        AUTOGRADING_LOG_PATH, JOB_ID,
        message="grade_scheduler.py launched"
    )

    # prepare a list of untrusted users to be used by the workers
    untrusted_users = multiprocessing.Queue()
    for i in range(num_workers):
        untrusted_users.put("untrusted" + str(i).zfill(2))

    # launch the worker threads
    address = my_stats['address']
    if address != 'localhost':
        which_machine = f"{my_stats['username']}@{address}"
    else:
        which_machine = address
    my_server = my_stats['server_name']
    processes = list()
    for i in range(0, num_workers):
        u = "untrusted" + str(i).zfill(2)
        p = multiprocessing.Process(
            target=worker_process, args=(which_machine, address, u, my_server)
        )
        p.start()
        processes.append(p)

    # main monitoring loop
    try:
        while True:
            alive = 0
            for i in range(0, num_workers):
                if processes[i].is_alive:
                    alive = alive+1
                else:
                    autograding_utils.log_message(
                        AUTOGRADING_LOG_PATH, JOB_ID,
                        message=f"ERROR: process {i} is not alive"
                    )
            if alive != num_workers:
                autograding_utils.log_message(
                    AUTOGRADING_LOG_PATH, JOB_ID,
                    message=f"ERROR: #workers={num_workers} != #alive={alive}"
                )
            time.sleep(1)

    except KeyboardInterrupt:
        autograding_utils.log_message(
            AUTOGRADING_LOG_PATH, JOB_ID,
            message="grade_scheduler.py keyboard interrupt"
        )

        # just kill everything in this group id right now
        # NOTE:  this may be a bug if the grandchildren have a different group id and not be killed
        os.kill(-os.getpid(), signal.SIGKILL)

        # run this to check if everything is dead
        #    ps  xao pid,ppid,pgid,sid,comm,user  | grep untrust

        # everything's dead, including the main process so the rest of this will be ignored
        # but this was mostly working...

        # terminate the jobs
        for i in range(0, num_workers):
            processes[i].terminate()

        # wait for them to join
        for i in range(0, num_workers):
            processes[i].join()

    autograding_utils.log_message(
        AUTOGRADING_LOG_PATH, JOB_ID,
        message="grade_scheduler.py terminated"
    )


# ==================================================================================
def read_autograding_worker_json():
    try:
        with open(ALL_WORKERS_JSON, 'r') as infile:
            name_and_stats = json.load(infile)
            # grab the key and the value. NOTE: For now there should only ever be one pair.
            name = list(name_and_stats.keys())[0]
            stats = name_and_stats[name]
    except FileNotFoundError as e:
        raise SystemExit(
            "autograding_worker.json not found. Have you registered this worker with a "
            "Submitty host yet?"
        ) from e
    except Exception as e:
        autograding_utils.log_stack_trace(AUTOGRADING_STACKTRACE_PATH, trace=traceback.format_exc())
        raise SystemExit("ERROR loading autograding_worker.json file: {0}".format(e))
    return name, stats


# ==================================================================================
# Removes any existing files or folders in the autograding_done folder.
def cleanup_old_jobs():
    for file_path in Path(SUBMITTY_DATA_DIR, "autograding_DONE").glob("*"):
        file_path = str(file_path)
        autograding_utils.log_message(
            AUTOGRADING_LOG_PATH, JOB_ID,
            message=f"Remove autograding DONE file: {file_path}"
        )
        try:
            os.remove(file_path)
        except Exception:
            autograding_utils.log_stack_trace(
                AUTOGRADING_STACKTRACE_PATH, JOB_ID,
                trace=traceback.format_exc()
            )

# ==================================================================================


if __name__ == "__main__":
    cleanup_old_jobs()
    print('cleaned up old jobs')
    my_name, my_stats = read_autograding_worker_json()
    launch_workers(my_name, my_stats)
