#!/usr/bin/env python3

"""
Basic migration script to handle the database
"""

from argparse import ArgumentParser
from collections import OrderedDict
from datetime import datetime
from importlib.machinery import SourceFileLoader
import json
import os
from pathlib import Path
import re
import sys

import psycopg2

VERSION = "1.3.0"
DIR_PATH = Path(__file__).parent.resolve()
MIGRATIONS_PATH = DIR_PATH / 'migrations'
ENVIRONMENTS = ['master', 'system', 'course']


class Config:
    def __init__(self, config_path):
        # These are set dynamically below and are here just so IDE doesn't complain about them not
        # existing
        self.database = self.submitty = self.submitty_users = None
        self.config_path = Path(config_path)
        for key in ('database', 'submitty', 'submitty_users'):
            self.__dict__[key] = self._get_data(key)

    def _get_data(self, filename):
        with Path(self.config_path, filename + '.json').open('r') as open_file:
            return json.load(open_file, object_pairs_hook=OrderedDict)


def parse_args():
    parser = ArgumentParser(description='Migration script for upgrading/downgrading the database')
    parser.add_argument('-v', '--version', action='version', version='%(prog)s {}'.format(VERSION))
    config_path = Path(DIR_PATH, '..', '..', '..', 'config')
    default_config = config_path.resolve() if config_path.exists() else None
    required = False if default_config is not None else True
    parser.add_argument('-c', '--config', dest='config_path', type=str, required=required, default=default_config)
    parser.add_argument('-e', '--environment', dest='environments', choices=ENVIRONMENTS, action='append')
    parser.add_argument('--course', dest='choose_course', nargs=2, metavar=('semester', 'course'), default=None)

    subparsers = parser.add_subparsers(metavar='command', dest='command')
    subparsers.required = True
    sub = subparsers.add_parser('create', help='Create migration')
    sub.add_argument('name', help='Name of argument')
    sub = subparsers.add_parser('migrate', help='Run migrations')
    sub.add_argument('--single', action='store_true', default=False, dest='single',
                     help='Only run one migration')
    sub.add_argument('--fake', action='store_true', default=False, dest='set_fake',
                     help='Mark migrations as run without actually running them')
    sub.add_argument('--initial', action='store_true', default=False,
                     help='Only run the first migration and then mark the rest as done without running them.')
    subparsers.add_parser('rollback', help='Rollback the previously done migration')
    args = parser.parse_args()
    if args.environments is None:
        args.environments = ENVIRONMENTS
    # make sure the order is of 'master', 'system', 'course' depending on what environments have been selected
    # system must be run after master initially as system relies on master DB being setup for migration table
    environments = []
    for env in ENVIRONMENTS:
        if env in args.environments:
            environments.append(env)
    args.environments = environments
    args.config = Config(args.config_path)
    return args


def main():
    args = parse_args()
    getattr(sys.modules[__name__], args.command)(args)


def create(args):
    now = datetime.now()
    ver = "{:04}{:02}{:02}{:02}{:02}{:02}".format(now.year, now.month, now.day, now.hour, now.minute, now.second)
    filename = "{}_{}".format(ver, args.name)
    check = re.search(r'[^A-Za-z0-9_\-]', filename)
    if check is not None:
        raise ValueError("Name '{}' contains invalid character '{}'".format(filename, check.group(0)))
    filename += '.py'
    for environment in args.environments:
        parameters = ['config']
        if environment in ['course', 'master']:
            parameters.append('conn')
        if environment == 'course':
            parameters.append('semester')
            parameters.append('course')
        with Path(MIGRATIONS_PATH, environment, filename).open('w') as open_file:
            open_file.write("""def up({0}):
    pass


def down({0}):
    pass""".format(', '.join(parameters)))


def migrate(args):
    args.direction = 'up'
    handle_migration(args)


def rollback(args):
    args.direction = 'down'
    handle_migration(args)


def handle_migration(args):
    for environment in args.environments:
        args.course = None
        args.semester = None
        if environment in ['master', 'system']:
            params = {
                'dbname': 'submitty',
                'host': args.config.database['database_host'],
                'user': args.config.database['database_user'],
                'password': args.config.database['database_password']
            }

            print("Running {} migrations for {}...".format(args.direction, environment), end="")
            with psycopg2.connect(**params) as connection:
                migrate_environment(connection, environment, args)

        if environment == 'course':
            params = {
                'host': args.config.database['database_host'],
                'user': args.config.database['database_user'],
                'password': args.config.database['database_password']
            }

            course_dir = os.path.join(args.config.submitty['submitty_data_dir'], 'courses')
            if not os.path.exists(course_dir):
                print("Could not find courses directory: {}".format(course_dir))
            else:
                for semester in os.listdir(course_dir):
                    for course in os.listdir(os.path.join(course_dir, semester)):
                        if args.choose_course is None or [semester, course] == args.choose_course:
                            args.semester = semester
                            args.course = course
                            print("Running {} migrations for {}.{}...".format(args.direction, semester, course), end="")
                            params['dbname'] = 'submitty_{}_{}'.format(semester, course)
                            try:
                                with psycopg2.connect(**params) as connection:
                                    migrate_environment(connection, environment, args)
                            except psycopg2.OperationalError:
                                print("Submitty Database Migration Warning:  Database does not exist for "
                                      "semester={} course={}".format(semester, course))


def migrate_environment(connection, environment, args):
    # Get the migration table and if it doesn't exist, then we have to create it
    with connection.cursor() as cursor:
        cursor.execute("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND "
                       "table_name='migrations_{}')".format(environment))
        exists = cursor.fetchone()[0]

    missing_migrations = OrderedDict()
    migrations = load_migrations(MIGRATIONS_PATH / environment)

    changes=False

    if exists:
        with connection.cursor() as cursor:
            cursor.execute('SELECT id, commit_time, status FROM migrations_{} ORDER BY id'.format(environment))
            for migration in cursor.fetchall():
                # fetchall returns things as a tuple which is a bit unwieldy
                migration = {'id': migration[0], 'commit_time': migration[1], 'status': migration[2]}
                if migration['id'] in migrations:
                    migrations[migration['id']].update({
                        'commit_time': migration['commit_time'],
                        'status': migration['status'],
                        'db': True
                    })
                else:
                    missing_migrations[migration['id']] = migration

    if len(missing_migrations) > 0:
        if not changes:
            print()
        print('Removing {} missing migrations:'.format(len(missing_migrations)))
        for key in missing_migrations:
            remove_migration(connection, missing_migrations[key], environment, args)
            changes=True
        print()

    args.fake = args.set_fake if 'set_fake' in args else False
    if args.direction == 'up':
        keys = list(migrations.keys())
        if args.initial is True:
            key = keys.pop(0)
            if not changes:
                print("")
                changes=True
            run_migration(connection, migrations[key], environment, args)
            args.fake = True
        for key in keys:
            if migrations[key]['status'] == 0:
                run_migration(connection, migrations[key], environment, args)
                if args.single:
                    break
    else:
        for key in reversed(list(migrations.keys())):
            if migrations[key]['status'] == 1:
                run_migration(connection, migrations[key], environment, args)
                break

    print("DONE")
    if changes:
        print()


def remove_migration(connection, migration, environment, args):
    print("  {}".format(migration['id']))
    file_path = Path(args.config.submitty['submitty_install_dir'], 'migrations', environment, migration['id'] + '.py')
    if file_path.exists():
        module = load_migration_module(migration['id'], file_path)
        call_func(getattr(module, 'down', noop), connection, environment, args)
        file_path.unlink()
    with connection.cursor() as cursor:
        cursor.execute('DELETE FROM migrations_{} WHERE id=%s'.format(environment), (migration['id'],))
    connection.commit()


def call_func(func, connection, environment, args):
    parameters = [args.config]
    if environment in ['course', 'master']:
        parameters.append(connection)
    if environment == 'course':
        parameters.append(args.semester)
        parameters.append(args.course)
    func(*parameters)


def noop(_):
    pass


def run_migration(connection, migration, environment, args):
    print("  {}{}".format(migration['id'], ' (FAKE)' if args.fake else ''))
    if not args.fake:
        call_func(getattr(migration['module'], args.direction, noop), connection, environment, args)
    status = 1 if args.direction == 'up' else 0
    with connection.cursor() as cursor:
        if migration['db']:
            cursor.execute('UPDATE migrations_{} SET commit_time=CURRENT_TIMESTAMP, status=%s WHERE id=%s'.format(environment), (status, migration['id'],))
        else:
            cursor.execute('INSERT INTO migrations_{} (id, status) VALUES(%s, %s)'.format(environment), (migration['id'], status,))
    connection.commit()


def load_migration_module(name, path):
    # TODO: change this to not use deprecated loader.load_module after dropping Python 3.4 support
    loader = SourceFileLoader(name, str(path))
    module = loader.load_module(name)
    return module


def load_migrations(path):
    migrations = OrderedDict()
    for migration in sorted(filter(lambda x: x.endswith('.py'), [x.name for x in path.iterdir()])):
        migration_id = migration[:-3]
        migrations[migration_id] = {
            'id': migration_id,
            'commit_time': None,
            'status': 0,
            'db': False,
            'module': load_migration_module(migration_id, path / migration)
        }
    return migrations


if __name__ == '__main__':
    main()
