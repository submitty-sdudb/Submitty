def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        # drop foreign key contraints as the foreign keys also store type of columns in their definitions
        cursor.execute("ALTER TABLE ONLY grading_registration DROP CONSTRAINT grading_registration_sections_registration_id_fkey")
        cursor.execute("ALTER TABLE ONLY users DROP CONSTRAINT users_registration_section_fkey")

        # typecast from integer to character varying
        cursor.execute("ALTER TABLE ONLY sections_registration ALTER COLUMN sections_registration_id SET DATA TYPE character varying(255) USING sections_registration_id::varchar(255)")
        cursor.execute("ALTER TABLE ONLY grading_registration ALTER COLUMN sections_registration_id SET DATA TYPE character varying(255) USING sections_registration_id::varchar(255)")
        cursor.execute("ALTER TABLE ONLY users ALTER COLUMN registration_section SET DATA TYPE character varying(255) USING registration_section::varchar(255)")
        cursor.execute("ALTER TABLE ONLY gradeable_teams ALTER COLUMN registration_section SET DATA TYPE character varying(255) USING registration_section::varchar(255)")

        # renable the foreign key contraints as we're done typecasting
        cursor.execute("ALTER TABLE ONLY grading_registration ADD CONSTRAINT grading_registration_sections_registration_id_fkey FOREIGN KEY (sections_registration_id) REFERENCES sections_registration(sections_registration_id)")
        cursor.execute("ALTER TABLE ONLY users ADD CONSTRAINT users_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES sections_registration(sections_registration_id)")

        # add missing foreign key contraints
        cursor.execute("ALTER TABLE ONLY gradeable_teams ADD CONSTRAINT gradeable_teams_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES sections_registration(sections_registration_id)")
        cursor.execute("ALTER TABLE ONLY gradeable_teams ADD CONSTRAINT gradeable_teams_rotating_section_fkey FOREIGN KEY (rotating_section) REFERENCES sections_rotating(sections_rotating_id)")


def down(config, conn, semester, course):
    with conn.cursor() as cursor:
        # drop foreign key contraints while we typecast the columns
        cursor.execute("ALTER TABLE ONLY gradeable_teams DROP CONSTRAINT gradeable_teams_registration_section_fkey")
        cursor.execute("ALTER TABLE ONLY gradeable_teams DROP CONSTRAINT gradeable_teams_rotating_section_fkey")

        # disable foreign key contraints while we typecast the columns
        cursor.execute("ALTER TABLE ONLY grading_registration DROP CONSTRAINT grading_registration_sections_registration_id_fkey")
        cursor.execute("ALTER TABLE ONLY users DROP CONSTRAINT users_registration_section_fkey")

        # typecast from character varying to integer
        cursor.execute("ALTER TABLE ONLY sections_registration ALTER COLUMN sections_registration_id SET DATA TYPE integer USING sections_registration_id::integer")
        cursor.execute("ALTER TABLE ONLY grading_registration ALTER COLUMN sections_registration_id SET DATA TYPE integer USING sections_registration_id::integer")
        cursor.execute("ALTER TABLE ONLY users ALTER COLUMN registration_section SET DATA TYPE integer USING registration_section::integer")
        cursor.execute("ALTER TABLE ONLY gradeable_teams ALTER COLUMN registration_section SET DATA TYPE integer USING registration_section::integer")

        # renable the foreign key contraints as we're done typecasting
        cursor.execute("ALTER TABLE ONLY grading_registration ADD CONSTRAINT grading_registration_sections_registration_id_fkey FOREIGN KEY (sections_registration_id) REFERENCES sections_registration(sections_registration_id)")
        cursor.execute("ALTER TABLE ONLY users ADD CONSTRAINT users_registration_section_fkey FOREIGN KEY (registration_section) REFERENCES sections_registration(sections_registration_id)")
