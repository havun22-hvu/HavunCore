/**
 * HavunCore Webapp Backend – startpunt
 * Koppelt o.a. de SQLite-database die door de PHP-indexer (HavunCore) wordt bijgehouden.
 */

const express = require('express');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

const app = express();
app.use(express.json());

// --- SQLite: dezelfde database als PHP (docs:index / HavunAIBridge) ---
// In HavunCore-repo staat het bestand in database/doc_intelligence.sqlite
const dbPath = process.env.HAVUNCORE_DB_PATH || path.join(__dirname, '../../database/doc_intelligence.sqlite');

const db = new sqlite3.Database(dbPath, (err) => {
    if (err) console.error('Database connectie fout:', err.message);
    else console.log('Verbonden met HavunCore Wisselwerking Database.');
});

app.locals.db = db;

// --- Routes ---
const orchestrateRouter = require('./routes/orchestrate');
app.use('/api', orchestrateRouter);

// --- Server ---
const PORT = process.env.PORT || 5175;
app.listen(PORT, () => {
    console.log(`HavunCore backend op poort ${PORT}`);
});

module.exports = app;
