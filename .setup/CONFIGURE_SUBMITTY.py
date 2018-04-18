#!/usr/bin/env python3

import argparse
from collections import OrderedDict
import grp
import json
import os
import pwd
import shutil
import tzlocal
import tempfile

def get_uid(user):
    return pwd.getpwnam(user).pw_uid


def get_gid(user):
    return pwd.getpwnam(user).pw_gid


def get_ids(user):
    try:
        return get_uid(user), get_gid(user)
    except KeyError:
        raise SystemExit("ERROR: Could not find user: " + user)


def get_input(question, default=""):
    add = "[{}] ".format(default) if default != "" else ""
    user = input("{}: {}".format(question, add)).strip()
    if user == "":
        user = default
    return user


##############################################################################
# this script must be run by root or sudo
if os.getuid() != 0:
    raise SystemExit('ERROR: This script must be run by root or sudo')


parser = argparse.ArgumentParser(description='Submitty configuration script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty to be in debug mode. '
                                                                        'This should not be used in production!')
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--data-dir', default='/var/local/submitty', help='Set the data directory for Submitty')

args = parser.parse_args()

# determine location of SUBMITTY GIT repository
# this script (CONFIGURES_SUBMITTY.py) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
SETUP_SCRIPT_DIRECTORY = os.path.dirname(os.path.realpath(__file__))
SUBMITTY_REPOSITORY = os.path.dirname(SETUP_SCRIPT_DIRECTORY)

# recommended (default) directory locations
# FIXME: Check that directories exist and are readable/writeable?
SUBMITTY_INSTALL_DIR = args.install_dir
if not os.path.isdir(SUBMITTY_INSTALL_DIR) or not os.access(SUBMITTY_INSTALL_DIR, os.R_OK | os.W_OK):
    raise SystemExit('Install directory {} does not exist or is not accessible'.format(SUBMITTY_INSTALL_DIR))

SUBMITTY_DATA_DIR = args.data_dir
if not os.path.isdir(SUBMITTY_DATA_DIR) or not os.access(SUBMITTY_DATA_DIR, os.R_OK | os.W_OK):
    raise SystemExit('Data directory {} does not exist or is not accessible'.format(SUBMITTY_DATA_DIR))

SUBMITTY_TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, 'GIT_CHECKOUT_Tutorial')

TAGRADING_LOG_PATH = os.path.join(SUBMITTY_DATA_DIR, 'logs')
AUTOGRADING_LOG_PATH = os.path.join(SUBMITTY_DATA_DIR, 'logs', 'autograding')

##############################################################################

# recommended names for special users & groups related to the SUBMITTY system
HWPHP_USER = 'hwphp'
HWPHP_GROUP = 'hwphp'
HWCGI_USER = 'hwcgi'
HWCRON_USER = 'hwcron'
HWCRON_GROUP = 'hwcron'

if not args.worker:
    HWPHP_UID, HWPHP_GID = get_ids(HWPHP_USER)
    HWCGI_UID, HWCGI_GID = get_ids(HWCGI_USER)
    # System Groups
    HWCRONPHP_GROUP = 'hwcronphp'
    try:
        grp.getgrnam(HWCRONPHP_GROUP)
    except KeyError:
        raise SystemExit("ERROR: Could not find group: " + HWCRONPHP_GROUP)

HWCRON_UID, HWCRON_GID = get_ids(HWCRON_USER)

COURSE_BUILDERS_GROUP = 'course_builders'
try:
    grp.getgrnam(COURSE_BUILDERS_GROUP)
except KeyError:
    raise SystemExit("ERROR: Could not find group: " + COURSE_BUILDERS_GROUP)

##############################################################################

# This value must be at least 60: assumed in INSTALL_SUBMITTY.sh generation of crontab
NUM_UNTRUSTED = 60

FIRST_UNTRUSTED_UID, FIRST_UNTRUSTED_GID = get_ids('untrusted00')

# confirm that the uid/gid of the untrusted users are sequential
for i in range(1, NUM_UNTRUSTED):
    untrusted_user = "untrusted{:0=2d}".format(i)
    uid, gid = get_ids(untrusted_user)
    if uid != FIRST_UNTRUSTED_UID + i:
        raise SystemExit('CONFIGURATION ERROR: untrusted UID not sequential: ' + untrusted_user)
    elif gid != FIRST_UNTRUSTED_GID + i:
        raise SystemExit('CONFIGURATION ERROR: untrusted GID not sequential: ' + untrusted_user)

##############################################################################

# adjust this number depending on the # of processors
# available on your hardware
if args.debug == False:
    NUM_GRADING_SCHEDULER_WORKERS = 5
else:
    NUM_GRADING_SCHEDULER_WORKERS = 1

##############################################################################

SETUP_INSTALL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, '.setup')
SETUP_REPOSITORY_DIR = os.path.join(SUBMITTY_REPOSITORY, '.setup')

CONFIGURATION_FILE = os.path.join(SETUP_INSTALL_DIR, 'INSTALL_SUBMITTY.sh')
CONFIGURATION_JSON = os.path.join(SETUP_INSTALL_DIR, 'submitty_conf.json')
SITE_CONFIG_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "site", "config")

##############################################################################

defaults = {'database_host': 'localhost',
            'database_user': 'hsdbu',
            'submission_url': '',
            'vcs_url': '',
            'authentication_method': 1,
            'institution_name' : '',
            'username_change_text' : 'Submitty welcomes individuals of all ages, backgrounds, citizenships, disabilities, sex, education, ethnicities, family statuses, genders, gender identities, geographical locations, languages, military experience, political views, races, religions, sexual orientations, socioeconomic statuses, and work experiences. In an effort to create an inclusive environment, you may specify a preferred name to be used instead of what was provided on the registration roster.',
            'institution_homepage' : '',
            'timezone' : tzlocal.get_localzone().zone}

loaded_defaults = {}
if os.path.isfile(CONFIGURATION_JSON):
    with open(CONFIGURATION_JSON) as conf_file:
        loaded_defaults = json.load(conf_file)
    #no need to authenticate on a worker machine (no website)
    if not args.worker:
        loaded_defaults['authentication_method'] = 1 if loaded_defaults['authentication_method'] == 'PamAuthentication' else 2

# grab anything not loaded in (useful for backwards compatibility if a new default is added that 
# is not in an existing config file.)
for key in defaults.keys():
    if key not in loaded_defaults:
        loaded_defaults[key] = defaults[key]
defaults = loaded_defaults

print("\nWelcome to the Submitty Homework Submission Server Configuration\n")
DEBUGGING_ENABLED = args.debug is True

if DEBUGGING_ENABLED:
    print('!! DEBUG MODE ENABLED !!')
    print()

if args.worker:
    print("CONFIGURING SUBMITTY AS A WORKER !!")

print('Hit enter to use default in []')
print()

if not args.worker:
    DATABASE_HOST = get_input('What is the database host?', defaults['database_host'])
    print()

    DATABASE_USER = get_input('What is the database user?', defaults['database_user'])
    print()

    default = ''
    if 'database_password' in defaults and DATABASE_USER == defaults['database_user']:
        default = '(Leave blank to use same password)'
    DATABASE_PASS = get_input('What is the database password for {}? {}'.format(DATABASE_USER, default))
    if DATABASE_PASS == '' and DATABASE_USER == defaults['database_user'] and 'database_password' in defaults:
        DATABASE_PASS = defaults['database_password']
    print()

    TIMEZONE = get_input('What timezone should Submitty use? (for a full list of supported timezones see http://php.net/manual/en/timezones.php)', defaults['timezone'])
    print()

    SUBMISSION_URL = get_input('What is the url for submission? (ex: http://192.168.56.101 or '
                               'https://submitty.cs.rpi.edu)', defaults['submission_url']).rstrip('/')
    print()

    VCS_URL = get_input('What is the url for VCS? (ex: http://192.168.56.102/git or https://submitty-vcs.cs.rpi.edu/git', defaults['vcs_url']).rstrip('/')
    print()

    INSTITUTION_NAME = get_input('What is the name of your institution? (Leave blank/type "none" if not desired)',
                             defaults['institution_name'])
    print()
    
    if INSTITUTION_NAME == '' or INSTITUTION_NAME.isspace():
        INSTITUTION_HOMEPAGE = ''
    else:
        INSTITUTION_HOMEPAGE = get_input("What is the url of your institution\'s homepage? "
                                     '(Leave blank/type "none" if not desired)', defaults['institution_homepage'])
        if INSTITUTION_HOMEPAGE.lower() == "none":
            INSTITUTION_HOMEPAGE = ''
        print()

    USERNAME_TEXT = defaults['username_change_text']

    print("What authentication method to use:\n1. PAM\n2. Database\n")
    while True:
        try:
            auth = int(get_input('Enter number?', defaults['authentication_method']))
        except ValueError:
            auth = 0
        if 0 < auth < 3:
            break
        print('Number must be between 0 and 3')
    print()

    if auth == 1:
        AUTHENTICATION_METHOD = 'PamAuthentication'
    else:
        AUTHENTICATION_METHOD = 'DatabaseAuthentication'

    TAGRADING_URL = SUBMISSION_URL + '/hwgrading'
    CGI_URL = SUBMISSION_URL + '/cgi-bin'


##############################################################################
# make the installation setup directory

if os.path.isdir(SETUP_INSTALL_DIR):
    shutil.rmtree(SETUP_INSTALL_DIR)
os.makedirs(SETUP_INSTALL_DIR, exist_ok=True)

shutil.chown(SETUP_INSTALL_DIR, 'root', COURSE_BUILDERS_GROUP)
os.chmod(SETUP_INSTALL_DIR, 0o751)

##############################################################################
# WRITE CONFIG FILES IN ${SUBMITTY_INSTALL_DIR}/.setup

config = OrderedDict()

config['submitty_install_dir'] = SUBMITTY_INSTALL_DIR
config['submitty_repository'] = SUBMITTY_REPOSITORY
config['submitty_data_dir'] = SUBMITTY_DATA_DIR

config['course_builders_group'] = COURSE_BUILDERS_GROUP

config['num_untrusted'] = NUM_UNTRUSTED
config['first_untrusted_uid'] = FIRST_UNTRUSTED_UID
config['first_untrusted_gid'] = FIRST_UNTRUSTED_UID
config['num_grading_scheduler_workers'] = NUM_GRADING_SCHEDULER_WORKERS


config['hwcron_user'] = HWCRON_USER
config['hwcron_uid'] = HWCRON_UID
config['hwcron_gid'] = HWCRON_GID    

if not args.worker:
    config['submitty_tutorial_dir'] = SUBMITTY_TUTORIAL_DIR

    config['hwphp_user'] = HWPHP_USER
    config['hwcgi_user'] = HWCGI_USER
    config['hwcronphp_group'] = HWCRONPHP_GROUP
    config['hwphp_uid'] = HWPHP_UID
    config['hwphp_gid'] = HWPHP_GID

    config['database_host'] = DATABASE_HOST
    config['database_user'] = DATABASE_USER
    config['database_password'] = DATABASE_PASS
    config['timezone'] = TIMEZONE

    config['authentication_method'] = AUTHENTICATION_METHOD
    config['vcs_url'] = VCS_URL
    config['submission_url'] = SUBMISSION_URL
    config['tagrading_url'] = TAGRADING_URL
    config['cgi_url'] = CGI_URL

    config['institution_name'] = INSTITUTION_NAME
    config['username_change_text'] = USERNAME_TEXT
    config['institution_homepage'] = INSTITUTION_HOMEPAGE
    config['debugging_enabled'] = DEBUGGING_ENABLED

    config['site_log_path'] = TAGRADING_LOG_PATH

config['autograding_log_path'] = AUTOGRADING_LOG_PATH

if args.worker:
    config['worker'] = 1
else:
    config['worker'] = 0


with open(CONFIGURATION_FILE, 'w') as open_file:
    def write(x=''):
        print(x, file=open_file)
    write('#!/bin/bash')
    write()

    write('# Variables prepared by CONFIGURE_SUBMITTY.py')
    write('# Manual editing is allowed (but will be clobbered if CONFIGURE_SUBMITTY.py is re-run)')
    write()

    for key, value in config.items():
        key = str(key).upper()
        if isinstance(value, str):
            # To escape a single quote in bash, use '\'' because bash is awful
            write("{}='{}'".format(key, value.replace("'", "'\''")))
        elif isinstance(value, bool):
            write('{}={}'.format(key, 'true' if value is True else 'false'))
        else:
            write('{}={}'.format(key, value))
    write()
    write('# Now actually run the installation script')
    write('source '+SETUP_REPOSITORY_DIR+'/INSTALL_SUBMITTY_HELPER.sh  "$@"')

os.chmod(CONFIGURATION_FILE, 0o700)

with open(CONFIGURATION_JSON, 'w') as json_file:
    json.dump(config, json_file, indent=2)

os.chmod(CONFIGURATION_JSON, 0o500)

##############################################################################
# Setup ${SUBMITTY_INSTALL_DIR}/config

CONFIG_INSTALL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, 'config')

DATABASE_JSON = os.path.join(CONFIG_INSTALL_DIR, 'database.json')
SUBMITTY_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty.json')
SUBMITTY_USERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty_users.json')
WORKERS_JSON = os.path.join(CONFIG_INSTALL_DIR, 'autograding_workers.json')

#If the workers.json exists, rescue it from the destruction of config (move it to a temp directory).
tmp_autograding_workers_file = ""
if not args.worker:
    if os.path.isfile(WORKERS_JSON):
        #make a tmp folder and copy autograding workers to it
        tmp_folder = tempfile.mkdtemp()
        tmp_autograding_workers_file = os.path.join(tmp_folder, "autograding_workers.json")
        os.rename(WORKERS_JSON, tmp_autograding_workers_file)

if os.path.isdir(CONFIG_INSTALL_DIR):
    shutil.rmtree(CONFIG_INSTALL_DIR)
os.makedirs(CONFIG_INSTALL_DIR, exist_ok=True)
shutil.chown(CONFIG_INSTALL_DIR, 'root', COURSE_BUILDERS_GROUP)
os.chmod(CONFIG_INSTALL_DIR, 0o755)

#If the workers.json exists, finish rescuing it (copy it back).
if not tmp_autograding_workers_file == "":
    #copy autograding workers back
    os.rename(tmp_autograding_workers_file, WORKERS_JSON)
    #remove the tmp folder
    os.removedirs(tmp_folder)
    #make sure the permissions are correct.
    shutil.chown(WORKERS_JSON, 'root', HWCRON_GROUP)
    os.chmod(WORKERS_JSON, 0o440)

##############################################################################
# WRITE CONFIG FILES IN ${SUBMITTY_INSTALL_DIR}/conf

if not args.worker:
    if not os.path.isfile(WORKERS_JSON):
        worker_dict = {
            "primary": {
                "capabilities": ["default"],
                "address": "localhost",
                "username": "",
                "num_autograding_workers": NUM_GRADING_SCHEDULER_WORKERS
            }
        }

        with open(WORKERS_JSON, 'w') as workers_file:
            json.dump(worker_dict, workers_file, indent=4)
    shutil.chown(WORKERS_JSON, 'root', HWCRON_GROUP)
    os.chmod(WORKERS_JSON, 0o440)

##############################################################################
# Write database json

if not args.worker:
    config = OrderedDict()
    config['authentication_method'] = AUTHENTICATION_METHOD
    config['database_host'] = DATABASE_HOST
    config['database_user'] = DATABASE_USER
    config['database_password'] = DATABASE_PASS
    config['debugging_enabled'] = DEBUGGING_ENABLED

    with open(DATABASE_JSON, 'w') as json_file:
        json.dump(config, json_file, indent=2)
    shutil.chown(DATABASE_JSON, HWPHP_USER, 'www-data')
    os.chmod(DATABASE_JSON, 0o440)

##############################################################################
# Write submitty json

config = OrderedDict()
config['submitty_install_dir'] = SUBMITTY_INSTALL_DIR
config['submitty_repository'] = SUBMITTY_REPOSITORY
config['submitty_data_dir'] = SUBMITTY_DATA_DIR
config['autograding_log_path'] = AUTOGRADING_LOG_PATH
config['timezone'] = tzlocal.get_localzone().zone

if not args.worker:
    config['submitty_tutorial_dir'] = SUBMITTY_TUTORIAL_DIR
    config['site_log_path'] = TAGRADING_LOG_PATH
    config['submission_url'] = SUBMISSION_URL
    config['vcs_url'] = VCS_URL
    config['tagrading_url'] = TAGRADING_URL
    config['cgi_url'] = CGI_URL
    config['institution_name'] = INSTITUTION_NAME
    config['username_change_text'] = USERNAME_TEXT
    config['institution_homepage'] = INSTITUTION_HOMEPAGE

with open(SUBMITTY_JSON, 'w') as json_file:
    json.dump(config, json_file, indent=2)
os.chmod(SUBMITTY_JSON, 0o444)

##############################################################################
# Write users json

config = OrderedDict()
config['num_grading_scheduler_workers'] = NUM_GRADING_SCHEDULER_WORKERS
config['num_untrusted'] = NUM_UNTRUSTED
config['first_untrusted_uid'] = FIRST_UNTRUSTED_UID
config['first_untrusted_gid'] = FIRST_UNTRUSTED_UID
config['hwcron_uid'] = HWCRON_UID
config['hwcron_gid'] = HWCRON_GID
config['hwcron_user'] = HWCRON_USER
config['course_builders_group'] = COURSE_BUILDERS_GROUP

if not args.worker:
    config['hwphp_uid'] = HWPHP_UID
    config['hwphp_gid'] = HWPHP_GID
    config['hwphp_user'] = HWPHP_USER
    config['hwcgi_user'] = HWCGI_USER
    config['hwcronphp_group'] = HWCRONPHP_GROUP

with open(SUBMITTY_USERS_JSON, 'w') as json_file:
    json.dump(config, json_file, indent=2)
shutil.chown(SUBMITTY_USERS_JSON, 'root', HWCRON_GROUP)
os.chmod(SUBMITTY_USERS_JSON, 0o440)

##############################################################################

print('Configuration completed. Now you may run the installation script')
print('    sudo ' + CONFIGURATION_FILE)
print('          or')
print('    sudo {} clean'.format(CONFIGURATION_FILE))
print("\n")
