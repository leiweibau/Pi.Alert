-- Atomic integrity and audit rules apply to every writer, including bulk deletes.

DROP TRIGGER IF EXISTS logical_reference_insert;

CREATE TRIGGER logical_reference_insert BEFORE INSERT ON Devices WHEN NEW.dev_LogicalDeviceID IS NOT NULL AND NOT EXISTS(SELECT 1 FROM LogicalDevices WHERE id=NEW.dev_LogicalDeviceID) BEGIN
SELECT RAISE(ABORT,'invalid logical device reference');
END;

DROP TRIGGER IF EXISTS logical_reference_update;

CREATE TRIGGER logical_reference_update BEFORE UPDATE OF dev_LogicalDeviceID ON Devices WHEN NEW.dev_LogicalDeviceID IS NOT NULL AND NOT EXISTS(SELECT 1 FROM LogicalDevices WHERE id=NEW.dev_LogicalDeviceID) BEGIN
SELECT RAISE(ABORT,'invalid logical device reference');
END;

DROP TRIGGER IF EXISTS logical_new_insert;

CREATE TRIGGER logical_new_insert AFTER INSERT ON Devices WHEN NEW.dev_LogicalDeviceID IS NOT NULL AND NEW.dev_NewDevice IS NOT 0 BEGIN
UPDATE Devices SET dev_NewDevice=0 WHERE rowid=NEW.rowid;
END;

DROP TRIGGER IF EXISTS logical_new_update;

CREATE TRIGGER logical_new_update AFTER UPDATE OF dev_LogicalDeviceID,dev_NewDevice ON Devices WHEN NEW.dev_LogicalDeviceID IS NOT NULL AND NEW.dev_NewDevice IS NOT 0 BEGIN
UPDATE Devices SET dev_NewDevice=0 WHERE rowid=NEW.rowid;
END;

DROP TRIGGER IF EXISTS logical_created;

CREATE TRIGGER logical_created AFTER INSERT ON LogicalDevices BEGIN
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceCreated','','ID: ' || NEW.id || '; name: ' || NEW.name
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (1);
END;

DROP TRIGGER IF EXISTS logical_renamed;

CREATE TRIGGER logical_renamed AFTER UPDATE OF name ON LogicalDevices WHEN OLD.name IS NOT NEW.name BEGIN
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceRenamed','','ID: ' || NEW.id || '; name: ' || NEW.name || '; previous name: ' || OLD.name
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (1);
END;

DROP TRIGGER IF EXISTS logical_edited;

CREATE TRIGGER logical_edited AFTER UPDATE OF device_type,owner,location,vendor,"group" ON LogicalDevices WHEN OLD.device_type IS NOT NEW.device_type OR OLD.owner IS NOT NEW.owner OR OLD.location IS NOT NEW.location OR OLD.vendor IS NOT NEW.vendor OR OLD."group" IS NOT NEW."group" BEGIN
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceEdited','','ID: ' || NEW.id || '; name: ' || NEW.name
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (1);
END;

DROP TRIGGER IF EXISTS logical_immutable_id;

CREATE TRIGGER logical_immutable_id BEFORE UPDATE OF id ON LogicalDevices WHEN OLD.id IS NOT NEW.id BEGIN
SELECT RAISE(ABORT,'logical device identity is immutable');
END;

DROP TRIGGER IF EXISTS logical_delete;

CREATE TRIGGER logical_delete BEFORE DELETE ON LogicalDevices BEGIN
UPDATE Devices SET dev_LogicalDeviceID=NULL WHERE dev_LogicalDeviceID=OLD.id;
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceDeleted','','ID: ' || OLD.id || '; name: ' || OLD.name
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (1);
END;

DROP TRIGGER IF EXISTS logical_member_insert;

CREATE TRIGGER logical_member_insert AFTER INSERT ON Devices WHEN NEW.dev_LogicalDeviceID IS NOT NULL BEGIN
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceAssigned','','ID: ' || NEW.dev_LogicalDeviceID || '; name: ' || (SELECT name FROM LogicalDevices WHERE id=NEW.dev_LogicalDeviceID) || '; MAC: ' || NEW.dev_MAC || '; new reset: ' || CASE WHEN NEW.dev_NewDevice<>0 THEN '1' ELSE '0' END
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (1);
UPDATE LogicalDevices SET empty_since=NULL WHERE id=NEW.dev_LogicalDeviceID;
END;

DROP TRIGGER IF EXISTS logical_member_update;

CREATE TRIGGER logical_member_update AFTER UPDATE OF dev_LogicalDeviceID ON Devices WHEN OLD.dev_LogicalDeviceID IS NOT NEW.dev_LogicalDeviceID BEGIN
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceUnassigned','','ID: ' || OLD.dev_LogicalDeviceID || '; name: ' || (SELECT name FROM LogicalDevices WHERE id=OLD.dev_LogicalDeviceID) || '; MAC: ' || OLD.dev_MAC
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (OLD.dev_LogicalDeviceID IS NOT NULL);
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceAssigned','','ID: ' || NEW.dev_LogicalDeviceID || '; name: ' || (SELECT name FROM LogicalDevices WHERE id=NEW.dev_LogicalDeviceID) || '; MAC: ' || NEW.dev_MAC || '; new reset: ' || CASE WHEN OLD.dev_NewDevice<>0 OR NEW.dev_NewDevice<>0 THEN '1' ELSE '0' END
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (NEW.dev_LogicalDeviceID IS NOT NULL);
UPDATE LogicalDevices SET empty_since=NULL WHERE id=NEW.dev_LogicalDeviceID;
UPDATE LogicalDevices SET empty_since=COALESCE(empty_since,datetime('now')) WHERE id=OLD.dev_LogicalDeviceID
AND NOT EXISTS(SELECT 1 FROM Devices WHERE dev_LogicalDeviceID=OLD.dev_LogicalDeviceID);
END;

DROP TRIGGER IF EXISTS logical_member_delete;

CREATE TRIGGER logical_member_delete AFTER DELETE ON Devices WHEN OLD.dev_LogicalDeviceID IS NOT NULL BEGIN
INSERT INTO pialert_journal(Journal_DateTime,LogClass,Trigger,LogString,Hash,Additional_Info)
    SELECT datetime('now','localtime'),'a_020',trigger,'LogStr_LogicalDeviceUnassigned','','ID: ' || OLD.dev_LogicalDeviceID || '; name: ' || (SELECT name FROM LogicalDevices WHERE id=OLD.dev_LogicalDeviceID) || '; MAC: ' || OLD.dev_MAC
    FROM LogicalDeviceContext WHERE id=1 AND suppress_journal=0 AND (1);
UPDATE LogicalDevices SET empty_since=COALESCE(empty_since,datetime('now')) WHERE id=OLD.dev_LogicalDeviceID
AND NOT EXISTS(SELECT 1 FROM Devices WHERE dev_LogicalDeviceID=OLD.dev_LogicalDeviceID);
END;
