-- 51呀呀 泛娱乐母站 数据库 schema (SQLite)
-- 母站 + 多子站共享同一套事件库/时间线系统

-- 子站表：game / ai / tech / star
CREATE TABLE IF NOT EXISTS sites (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    slug        TEXT NOT NULL UNIQUE,   -- game, ai, tech, star
    name        TEXT NOT NULL,          -- 游戏呀呀
    subdomain   TEXT NOT NULL,          -- game.51chigua.com
    description TEXT,
    created_at  TEXT DEFAULT (datetime('now'))
);

-- 栏目表（一个子站下的核心栏目，如"王者荣耀事件"）
CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id    INTEGER NOT NULL,
    slug       TEXT NOT NULL,
    name       TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    UNIQUE(site_id, slug),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- 事件表（核心）
CREATE TABLE IF NOT EXISTS events (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id      INTEGER NOT NULL,
    category_id  INTEGER,
    slug         TEXT NOT NULL UNIQUE,
    title        TEXT NOT NULL,           -- SEO 标题
    summary      TEXT,                    -- 事件简介
    status       TEXT DEFAULT 'fermenting', -- fermenting/responded/ended 发酵中/已回应/已结束
    content_type TEXT DEFAULT 'news',     -- news 快讯 / analysis 解读 / wiki 百科
    body         TEXT,                    -- 正文(markdown/html)
    views        INTEGER DEFAULT 0,
    first_seen   TEXT DEFAULT (datetime('now')),
    updated_at   TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- 时间线表
CREATE TABLE IF NOT EXISTS timelines (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id  INTEGER NOT NULL,
    happened_at TEXT,                     -- 发生时间（自由文本，如"2026-05-28 14:00"）
    title     TEXT NOT NULL,
    detail    TEXT,
    sort_order INTEGER DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id)
);

-- 人物表
CREATE TABLE IF NOT EXISTS persons (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    slug   TEXT NOT NULL UNIQUE,
    name   TEXT NOT NULL,
    intro  TEXT
);

-- 事件-人物 关联
CREATE TABLE IF NOT EXISTS event_persons (
    event_id  INTEGER NOT NULL,
    person_id INTEGER NOT NULL,
    PRIMARY KEY (event_id, person_id)
);

-- 观点聚合（玩家观点/各方回应）
CREATE TABLE IF NOT EXISTS opinions (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id  INTEGER NOT NULL,
    source    TEXT,                       -- 来源：微博/NGA/B站...
    side      TEXT DEFAULT 'player',      -- player玩家 / official官方 / media媒体
    content   TEXT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id)
);

-- 采集队列（Step1 热点采集落地）
CREATE TABLE IF NOT EXISTS raw_feeds (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    source     TEXT,
    raw_title  TEXT,
    raw_text   TEXT,
    processed  INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_events_site ON events(site_id);
CREATE INDEX IF NOT EXISTS idx_events_updated ON events(updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_timelines_event ON timelines(event_id);
