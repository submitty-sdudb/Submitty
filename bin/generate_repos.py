#!/usr/bin/env python3
"""
This script will generate the repositories for a specified course and semester
for each student that currently does not have a repository. You can either make
the repositories at a per course level (for a repo that would carry through
all gradeables for example) or on a per gradeable level.
"""

import argparse
import json
import os
import sys
import shutil
from sqlalchemy import create_engine, MetaData, Table, bindparam

from submitty_utils import db_utils
CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON = json.load(open_file)
DAEMONCGI_GROUP = JSON['daemoncgi_group']

with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
    JSON = json.load(open_file)
DATABASE_HOST = JSON['database_host']
DATABASE_PORT = JSON['database_port']
DATABASE_USER = JSON['database_user']
DATABASE_PASS = JSON['database_password']

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON = json.load(open_file)
VCS_FOLDER = os.path.join(JSON['submitty_data_dir'], 'vcs', 'git')


def create_folder(folder):
    if not os.path.isdir(folder):
        os.makedirs(folder, mode=0o770)
        os.chdir(folder)
        os.system('git init --bare --shared')
        for root, dirs, files in os.walk(folder):
            for entry in files + dirs:
                shutil.chown(os.path.join(root, entry), group=DAEMONCGI_GROUP)


parser = argparse.ArgumentParser(description="Generate git repositories for a specific course and homework")
parser.add_argument("--non-interactive", action='store_true', default=False)
parser.add_argument("semester", help="semester")
parser.add_argument("course", help="course code")
parser.add_argument("repo_name", help="repository name")
args = parser.parse_args()

conn_string = db_utils.generate_connect_string(
    DATABASE_HOST,
    DATABASE_PORT,
    "submitty",
    DATABASE_USER,
    DATABASE_PASS,
)

engine = create_engine(conn_string)
connection = engine.connect()
metadata = MetaData(bind=engine)

courses_table = Table('courses', metadata, autoload=True)
select = courses_table.select().where(courses_table.c.semester == bindparam('semester')).where(courses_table.c.course == bindparam('course'))
course = connection.execute(select, semester=args.semester, course=args.course).fetchone()

if course is None:
    raise SystemExit("Semester '{}' and Course '{}' not found".format(args.semester, args.course))

vcs_semester = os.path.join(VCS_FOLDER, args.semester)
if not os.path.isdir(vcs_semester):
    os.makedirs(vcs_semester, mode=0o770, exist_ok=True)
    shutil.chown(vcs_semester, group=DAEMONCGI_GROUP)

vcs_course = os.path.join(vcs_semester, args.course)
if not os.path.isdir(vcs_course):
    os.makedirs(vcs_course, mode=0o770, exist_ok=True)
    shutil.chown(vcs_course, group=DAEMONCGI_GROUP)

is_team = False

# We will always pass in the name of the desired repository.
#
# If the repository name matches the name of an existing gradeable in
# the course, we will check if it's a team gradeable and create
# individual or team repos as appropriate.
#
# If it's not an existing gradeable, we will ask the user for
# confirmation, and make individual repos if requested.

course_conn_string = db_utils.generate_connect_string(
    DATABASE_HOST,
    DATABASE_PORT,
    f"submitty_{args.semester}_{args.course}",
    DATABASE_USER,
    DATABASE_PASS,
)

course_engine = create_engine(course_conn_string)
course_connection = course_engine.connect()
course_metadata = MetaData(bind=course_engine)

eg_table = Table('electronic_gradeable', course_metadata, autoload=True)
select = eg_table.select().where(eg_table.c.g_id == bindparam('gradeable_id'))
eg = course_connection.execute(select, gradeable_id=args.repo_name).fetchone()

is_team = False
if eg is not None:
    is_team = eg.eg_team_assignment
elif not args.non_interactive:
    print ("Warning: Semester '{}' and Course '{}' does not contain gradeable_id '{}'.".format(args.semester, args.course, args.repo_name))
    response = input ("Should we continue and make individual repositories named '"+args.repo_name+"' for each student? (y/n) ")
    if not response.lower() == 'y':
        print ("exiting");
        sys.exit()

if not os.path.isdir(os.path.join(vcs_course, args.repo_name)):
    os.makedirs(os.path.join(vcs_course, args.repo_name), mode=0o770)
    shutil.chown(os.path.join(vcs_course, args.repo_name), group=DAEMONCGI_GROUP)


if is_team:
    teams_table = Table('gradeable_teams', course_metadata, autoload=True)
    select = teams_table.select().where(teams_table.c.g_id == bindparam('gradeable_id')).order_by(teams_table.c.team_id)
    teams = course_connection.execute(select, gradeable_id=args.repo_name)

    for team in teams:
        create_folder(os.path.join(vcs_course, args.repo_name, team.team_id))

else:
    users_table = Table('courses_users', metadata, autoload=True)
    select = users_table.select().where(users_table.c.semester == bindparam('semester')).where(users_table.c.course == bindparam('course')).order_by(users_table.c.user_id)
    users = connection.execute(select, semester=args.semester, course=args.course)

    for user in users:
        create_folder(os.path.join(vcs_course, args.repo_name, user.user_id))
