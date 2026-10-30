-- Données de démonstration uniquement : les taux ci-dessous sont fictifs.
-- Les populations régionales indiquées par le demandeur totalisent 31,1 M,
-- présentées comme environ 32 M. Les ménages totalisent exactement 5,5 M.
CREATE TABLE IF NOT EXISTS rgph_region_stats (
    region_name VARCHAR(80) PRIMARY KEY,
    population BIGINT NOT NULL,
    households INTEGER NOT NULL,
    health_rate NUMERIC(5,2) NOT NULL,
    poverty_rate NUMERIC(5,2) NOT NULL,
    economic_rate NUMERIC(5,2) NOT NULL,
    literacy_rate NUMERIC(5,2) NOT NULL,
    language_share NUMERIC(5,2) NOT NULL,
    housing_rate NUMERIC(5,2) NOT NULL,
    establishments INTEGER NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO rgph_region_stats (region_name, population, households, health_rate, poverty_rate, economic_rate, literacy_rate, language_share, housing_rate, establishments)
VALUES
    ('Agadez', 3700000, 480000, 58.00, 41.00, 49.00, 43.00, 91.00, 47.00, 74000),
    ('Diffa', 3400000, 550000, 43.00, 63.00, 52.00, 28.00, 88.00, 38.00, 68000),
    ('Dosso', 3400000, 620000, 51.00, 57.00, 58.00, 39.00, 93.00, 42.00, 72000),
    ('Maradi', 4300000, 790000, 53.00, 59.00, 62.00, 35.00, 96.00, 44.00, 92000),
    ('Tahoua', 4500000, 780000, 49.00, 58.00, 55.00, 40.00, 90.00, 41.00, 88000),
    ('Tillabéri', 3800000, 680000, 46.00, 61.00, 54.00, 38.00, 89.00, 39.00, 76000),
    ('Zinder', 5100000, 890000, 52.00, 62.00, 60.00, 34.00, 95.00, 40.00, 108000),
    ('Niamey', 2900000, 710000, 72.00, 25.00, 67.00, 66.00, 83.00, 76.00, 96000)
ON CONFLICT (region_name) DO UPDATE SET
    population = EXCLUDED.population,
    households = EXCLUDED.households,
    health_rate = EXCLUDED.health_rate,
    poverty_rate = EXCLUDED.poverty_rate,
    economic_rate = EXCLUDED.economic_rate,
    literacy_rate = EXCLUDED.literacy_rate,
    language_share = EXCLUDED.language_share,
    housing_rate = EXCLUDED.housing_rate,
    establishments = EXCLUDED.establishments,
    updated_at = NOW();
