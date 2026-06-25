INSERT INTO llx_wurthpunchout_unitmap (entity, wurth_unit, fk_unit, label, date_creation)
SELECT 1, 'PCE', NULL, 'Pièce', NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_wurthpunchout_unitmap WHERE entity = 1 AND wurth_unit = 'PCE');

INSERT INTO llx_wurthpunchout_unitmap (entity, wurth_unit, fk_unit, label, date_creation)
SELECT 1, 'EA', NULL, 'Pièce', NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_wurthpunchout_unitmap WHERE entity = 1 AND wurth_unit = 'EA');

INSERT INTO llx_wurthpunchout_unitmap (entity, wurth_unit, fk_unit, label, date_creation)
SELECT 1, 'BOX', NULL, 'Boîte', NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_wurthpunchout_unitmap WHERE entity = 1 AND wurth_unit = 'BOX');

INSERT INTO llx_wurthpunchout_unitmap (entity, wurth_unit, fk_unit, label, date_creation)
SELECT 1, 'M', NULL, 'Mètre', NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_wurthpunchout_unitmap WHERE entity = 1 AND wurth_unit = 'M');

INSERT INTO llx_wurthpunchout_unitmap (entity, wurth_unit, fk_unit, label, date_creation)
SELECT 1, 'L', NULL, 'Litre', NOW()
WHERE NOT EXISTS (SELECT 1 FROM llx_wurthpunchout_unitmap WHERE entity = 1 AND wurth_unit = 'L');
