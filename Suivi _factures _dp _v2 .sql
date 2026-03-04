-- ============================================================
--  suivi_factures_dp_v2.sql - Schema complet avec workflow
--  PFE ASMA 2025-2026
--  IMPORTANT: Supprimer l'ancienne base avant d'importer !
-- ============================================================

DROP DATABASE IF EXISTS suivi_factures_dp;
CREATE DATABASE suivi_factures_dp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE suivi_factures_dp;

-- ── UTILISATEURS ────────────────────────────────────────────
CREATE TABLE utilisateurs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','gestionnaire','secretaire','user') NOT NULL DEFAULT 'user',
    region        VARCHAR(100) DEFAULT NULL,
    actif         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── FOURNISSEURS ─────────────────────────────────────────────
CREATE TABLE fournisseurs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(150) NOT NULL,
    adresse    TEXT         DEFAULT NULL,
    telephone  VARCHAR(30)  DEFAULT NULL,
    email      VARCHAR(150) DEFAULT NULL,
    nif        VARCHAR(50)  DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ── BORDEREAUX ───────────────────────────────────────────────
-- Cree par l'agent region, contient plusieurs factures
CREATE TABLE bordereaux (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    numero_bordereau VARCHAR(50)  NOT NULL UNIQUE,
    region           VARCHAR(100) NOT NULL,
    created_by       INT          NOT NULL,
    statut           ENUM('envoye','recu','en_traitement','cloture') NOT NULL DEFAULT 'envoye',
    date_envoi       DATE         NOT NULL DEFAULT (CURDATE()),
    date_reception   DATE         DEFAULT NULL,
    recu_par         INT          DEFAULT NULL COMMENT "secretaire qui a confirme reception",
    commentaire      TEXT         DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utilisateurs(id) ON DELETE RESTRICT,
    FOREIGN KEY (recu_par)   REFERENCES utilisateurs(id) ON DELETE SET NULL
);

-- ── FACTURES ─────────────────────────────────────────────────
CREATE TABLE factures (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    bordereau_id    INT            NOT NULL,
    numero_facture  VARCHAR(50)    NOT NULL UNIQUE,
    fournisseur_id  INT            NOT NULL,
    numero_contrat  VARCHAR(80)    DEFAULT NULL,
    montant         DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
    devise          ENUM('DZD','EUR','USD') NOT NULL DEFAULT 'DZD',
    date_emission   DATE           NOT NULL,
    date_echeance   DATE           DEFAULT NULL,
    statut          ENUM('en_attente','en_traitement','validee','rejetee') NOT NULL DEFAULT 'en_attente',
    motif_refus     TEXT           DEFAULT NULL,
    traite_par      INT            DEFAULT NULL COMMENT "gestionnaire qui a traite",
    date_traitement DATETIME       DEFAULT NULL,
    description     TEXT           DEFAULT NULL,
    created_by      INT            NOT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bordereau_id)  REFERENCES bordereaux(id)    ON DELETE RESTRICT,
    FOREIGN KEY (fournisseur_id)REFERENCES fournisseurs(id)  ON DELETE RESTRICT,
    FOREIGN KEY (created_by)    REFERENCES utilisateurs(id)  ON DELETE RESTRICT,
    FOREIGN KEY (traite_par)    REFERENCES utilisateurs(id)  ON DELETE SET NULL
);

-- ── NOTIFICATIONS ────────────────────────────────────────────
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL COMMENT "destinataire",
    facture_id  INT          DEFAULT NULL,
    bordereau_id INT         DEFAULT NULL,
    type        ENUM('refus','validation','reception','info') NOT NULL DEFAULT 'info',
    message     TEXT         NOT NULL,
    lu          TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (facture_id)  REFERENCES factures(id)     ON DELETE CASCADE,
    FOREIGN KEY (bordereau_id)REFERENCES bordereaux(id)   ON DELETE CASCADE
);

-- ── HISTORIQUE ───────────────────────────────────────────────
CREATE TABLE historique_factures (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    facture_id     INT         NOT NULL,
    user_id        INT         NOT NULL,
    ancien_statut  VARCHAR(50) DEFAULT NULL,
    nouveau_statut VARCHAR(50) NOT NULL,
    commentaire    TEXT        DEFAULT NULL,
    date_action    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facture_id) REFERENCES factures(id)     ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES utilisateurs(id) ON DELETE RESTRICT
);

-- ============================================================
--  DONNEES DE TEST (mots de passe initialises via init_password.php)
-- ============================================================
INSERT INTO utilisateurs (nom, prenom, username, email, password_hash, role, region) VALUES
('beghdad',  'Asma',   'asma.beghdad', 'B.asma@dp.dz',     'tmp', 'secretaire',   NULL),
('bouzouad', 'aymen',  'b.bouzouad',   'A.bouzouad@dp.dz', 'tmp', 'gestionnaire', NULL),
('Meziane',  'Sara',   's.meziane',    's.mez@dp.dz',       'tmp', 'user',         'Region Est'),
('Khaldi',   'Raouf',  'r.khaldi',     'r.khaldi@dp.dz',   'tmp', 'user',         'Region Ouest'),
('Admin',    'System', 'admin',         'admin@dp.dz',      'tmp', 'admin',        NULL);

INSERT INTO fournisseurs (nom, adresse, telephone, email, nif) VALUES
('SARL InfoTech',         'Alger, Rue des Oliviers 12',     '0550001122', 'contact@infotech.dz', 'NIF001'),
('EURL Materiaux Pro',    'Oran, Bd Colonel Lotfi 5',       '0550003344', 'mat.pro@mail.dz',     'NIF002'),
('EURL Trans Logistique', 'Constantine, Zone Industrielle', '0550005566', 'trans@log.dz',        'NIF003'),
('SPA Fournitures',       'Setif, Cite 20 Aout',            '0550007788', 'fourni@mail.dz',      'NIF004'),
('SARL Equipement Sud',   'Ouargla, Route Nationale',       '0550009900', 'equip@sud.dz',        'NIF005');