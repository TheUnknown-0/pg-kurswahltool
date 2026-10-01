-- Abgabe der Wahl und Kurzfassung für Admin-Liste und Export

ALTER TABLE selections
    ADD COLUMN submitted_at DATETIME NULL AFTER version,
    -- Vom Planer berechnet: Fehlerzahl, Rollen, Kurse je Halbjahr, Formularfelder (für den PDF-Export)
    ADD COLUMN summary JSON NULL AFTER submitted_at;
