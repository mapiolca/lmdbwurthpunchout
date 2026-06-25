ALTER TABLE llx_lmdbwurthpunchout_unitmap ADD UNIQUE INDEX uk_lmdbwurthpunchout_unitmap (entity, wurth_unit);
ALTER TABLE llx_lmdbwurthpunchout_unitmap ADD INDEX idx_lmdbwurthpunchout_unitmap_unit (fk_unit);
