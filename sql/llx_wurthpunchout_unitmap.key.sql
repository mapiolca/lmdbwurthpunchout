ALTER TABLE llx_wurthpunchout_unitmap ADD UNIQUE INDEX uk_wurthpunchout_unitmap (entity, wurth_unit);
ALTER TABLE llx_wurthpunchout_unitmap ADD INDEX idx_wurthpunchout_unitmap_unit (fk_unit);
