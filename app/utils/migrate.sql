CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(255) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
);

DROP PROCEDURE IF EXISTS campusdrive_migrate_to_1_0;

DELIMITER //

CREATE PROCEDURE campusdrive_migrate_to_1_0()
BEGIN
    DECLARE current_version VARCHAR(32) DEFAULT NULL;
    DECLARE current_version_number DECIMAL(10, 2);

    SELECT value
    INTO current_version
    FROM settings
    WHERE setting_key = 'db_version'
    LIMIT 1;

    IF current_version IS NULL THEN
        INSERT INTO settings (setting_key, value)
        VALUES ('db_version', '1.0');
    ELSEIF current_version NOT REGEXP '^[0-9]+([.][0-9]+)?$' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid db_version value; expected a numeric version.';
    ELSE
        SET current_version_number = CAST(current_version AS DECIMAL(10, 2));

        IF current_version_number > 1.0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Database version is newer than this migration script.';
        ELSEIF current_version_number < 1.0 THEN
            UPDATE settings
            SET value = '1.0'
            WHERE setting_key = 'db_version';
            ALTER TABLE users
                ADD COLUMN IF NOT EXISTS activation_token VARCHAR(255) DEFAULT NULL AFTER password,
                ADD COLUMN IF NOT EXISTS totp VARCHAR(32) DEFAULT NULL AFTER activation_token;
        END IF;
    END IF;
END//

DELIMITER ;

CALL campusdrive_migrate_to_1_0();
DROP PROCEDURE campusdrive_migrate_to_1_0;
