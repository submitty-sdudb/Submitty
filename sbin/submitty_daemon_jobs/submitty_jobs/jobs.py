"""
"""

from abc import ABC, abstractmethod
import os
import subprocess

from . import DATA_DIR


class AbstractJob(ABC):
    def __init__(self, job_details):
        self.job_details = job_details

    @abstractmethod
    def run_job(self):
        pass

    def cleanup_job(self):
        pass


class BuildConfig(AbstractJob):
    def run_job(self):
        semester = self.job_details['semester']
        course = self.job_details['course']
        gradeable = self.job_details['gradeable']

        build_script = os.path.join(DATA_DIR, 'courses', semester, course, 'BUILD_{}.sh'.format(course))
        build_output = os.path.join(DATA_DIR, 'courses', semester, course, 'build_script_output.txt')

        with open(build_output, "w") as output_file:
            subprocess.call([build_script, gradeable], stdout=output_file, stderr=output_file)
