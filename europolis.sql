-- =============================================================
-- Europolis - Schema baza de date
-- Motor: MySQL 5.7+ / MariaDB 10.3+ | Charset: utf8mb4
-- =============================================================

CREATE DATABASE IF NOT EXISTS europolis
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE europolis;

-- -------------------------------------------------------------
-- 1. games
--    Stocheaza fiecare sesiune de joc.
--    current_player_id este INT simplu, pentru a evita dependenta
--    circulara cu tabela players.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS games (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    status            ENUM('waiting','active','finished')
                                       NOT NULL DEFAULT 'waiting',
    max_players       TINYINT UNSIGNED NOT NULL,
    current_player_id INT UNSIGNED     NULL DEFAULT NULL,
    created_at        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. players
--    Fiecare jucator apartine unui joc.
--    ON DELETE CASCADE: daca jocul este sters, jucatorii dispar.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS players (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    game_id    INT UNSIGNED     NOT NULL,
    name       VARCHAR(100)     NOT NULL,
    balance    INT              NOT NULL DEFAULT 1500,
    position   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status     ENUM('active','bankrupt')
                                NOT NULL DEFAULT 'active',
    in_jail    TINYINT(1)       NOT NULL DEFAULT 0,
    jail_turns TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_players_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. owned_properties
--    Proprietatile detinute de jucatori.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS owned_properties (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    game_id             INT UNSIGNED     NOT NULL,
    player_id           INT UNSIGNED     NOT NULL,
    property_tile_index TINYINT UNSIGNED NOT NULL,
    purchase_price      INT              NOT NULL,
    building_level      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_game_property (game_id, property_tile_index),

    CONSTRAINT fk_owned_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_owned_player
        FOREIGN KEY (player_id) REFERENCES players(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. turns
--    O tura = o aruncare de zaruri a unui jucator.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS turns (
    id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    game_id        INT UNSIGNED     NOT NULL,
    player_id      INT UNSIGNED     NOT NULL,
    dice1          TINYINT UNSIGNED NOT NULL,
    dice2          TINYINT UNSIGNED NOT NULL,
    from_position  TINYINT UNSIGNED NOT NULL,
    to_position    TINYINT UNSIGNED NOT NULL,
    created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_turns_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_turns_player
        FOREIGN KEY (player_id) REFERENCES players(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. actions
--    Actiunile executate in cadrul unei ture.
--    details stocheaza un string JSON cu parametri.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS actions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    turn_id     INT UNSIGNED NOT NULL,
    game_id     INT UNSIGNED NOT NULL,
    action_type VARCHAR(50)  NOT NULL,
    details     TEXT         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_actions_turn
        FOREIGN KEY (turn_id) REFERENCES turns(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_actions_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. trades
--    Propuneri de schimb intre doi jucatori.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trades (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    game_id        INT UNSIGNED NOT NULL,
    from_player_id INT UNSIGNED NOT NULL,
    to_player_id   INT UNSIGNED NOT NULL,
    offered_money  INT UNSIGNED NOT NULL DEFAULT 0,
    status         ENUM('pending','accepted','rejected')
                                NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_trades_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_trades_from_player
        FOREIGN KEY (from_player_id) REFERENCES players(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_trades_to_player
        FOREIGN KEY (to_player_id) REFERENCES players(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. trade_properties
--    Proprietatile incluse intr-un trade.
--    direction:
--      offered   = oferite de fromPlayer
--      requested = cerute de fromPlayer de la toPlayer
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trade_properties (
    id                  INT UNSIGNED                NOT NULL AUTO_INCREMENT,
    trade_id            INT UNSIGNED                NOT NULL,
    property_tile_index TINYINT UNSIGNED            NOT NULL,
    direction           ENUM('offered','requested') NOT NULL,

    PRIMARY KEY (id),
    CONSTRAINT fk_trade_props_trade
        FOREIGN KEY (trade_id) REFERENCES trades(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. chat_messages
--    Mesaje scurte intre jucatorii aceluiasi joc.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    game_id    INT UNSIGNED  NOT NULL,
    player_id  INT UNSIGNED  NOT NULL,
    message    VARCHAR(500)  NOT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_chat_game_created (game_id, created_at),

    CONSTRAINT fk_chat_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_chat_player
        FOREIGN KEY (player_id) REFERENCES players(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
