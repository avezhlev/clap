ALTER TABLE `cdr` 
CHANGE COLUMN `IncomingProtocolCallRef` `IncomingProtocolCallRef` VARCHAR(64) CHARACTER SET 'utf8' NOT NULL ,
CHANGE COLUMN `OutgoingProtocolCallRef` `OutgoingProtocolCallRef` VARCHAR(64) CHARACTER SET 'utf8' NOT NULL ;
