-- Example table. Put its creation into the host application's normal migrations.
CREATE TABLE ocpp_charge_points (
    identity VARCHAR(255) PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    enabled SMALLINT NOT NULL DEFAULT 1
);
