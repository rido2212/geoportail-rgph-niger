-- Tables RGPH pour les communes et les indicateurs désagrégés.
-- Les taux sont des estimations de démonstration, pas des statistiques officielles.
CREATE TABLE IF NOT EXISTS commune (
    id BIGSERIAL PRIMARY KEY,
    region_nom VARCHAR(80) NOT NULL,
    departement_nom VARCHAR(120) NOT NULL,
    nom VARCHAR(140) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT commune_geo_unique UNIQUE (region_nom, departement_nom, nom)
);

CREATE INDEX IF NOT EXISTS idx_commune_region_departement
    ON commune (region_nom, departement_nom);

CREATE TABLE IF NOT EXISTS rgph_area_indicators (
    id BIGSERIAL PRIMARY KEY,
    area_level VARCHAR(16) NOT NULL CHECK (area_level IN ('department', 'commune')),
    region_name VARCHAR(80) NOT NULL,
    department_name VARCHAR(120) NOT NULL,
    commune_name VARCHAR(140) NOT NULL DEFAULT '',
    population BIGINT NOT NULL DEFAULT 0,
    households BIGINT NOT NULL DEFAULT 0,
    health_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
    poverty_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
    economic_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
    literacy_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
    language_share NUMERIC(5,2) NOT NULL DEFAULT 0,
    housing_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
    establishments INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT rgph_area_geo_unique UNIQUE (area_level, region_name, department_name, commune_name)
);

CREATE INDEX IF NOT EXISTS idx_rgph_area_region_level
    ON rgph_area_indicators (region_name, area_level);

CREATE INDEX IF NOT EXISTS idx_rgph_area_department
    ON rgph_area_indicators (region_name, department_name);
