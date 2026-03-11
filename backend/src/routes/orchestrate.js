/**
 * Hybrid Flow: Command-R (lokaal) filtert context → Claude krijgt alleen essence.
 * Optimaliseert 64 GB RAM + Command-R en bespaart Anthropic-tokens.
 *
 * De Motor (HavunCore): Blijft indexeren via PHP. Het Filter (Command-R): 15k+ → essentie.
 * Het Brein (Claude): Krijgt alleen de krenten uit de pap; Fase 4 (Code Agent) kan edit_file aansturen.
 */

const express = require('express');
const router = express.Router();
const Anthropic = require('@anthropic-ai/sdk');

const anthropic = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY });

function dbAll(db, sql, params = []) {
    return new Promise((resolve, reject) => {
        db.all(sql, params, (err, rows) => (err ? reject(err) : resolve(rows || [])));
    });
}

// STAP 1: Lokaal zoeken (Ranking)
// Haal top 15 fragmenten uit SQLite (zelfde DB als PHP HavunAIBridge / docs:index)
// Voor echte "top 15 op relevantie": gebruik similarity-search of HavunCore API
async function getRawContext(db, project) {
    if (project) {
        return dbAll(db, 'SELECT content, file_path FROM doc_embeddings WHERE project = ? LIMIT 15', [project]);
    }
    return dbAll(db, 'SELECT content, file_path FROM doc_embeddings LIMIT 15');
}

// STAP 2: Lokaal filteren met Command-R (64GB RAM)
// Ollama houdt alleen ECHT relevante code/info over voor de instruction
async function callOllamaFilter(instruction, rawContext) {
    const contextBlob = rawContext.map((r) => `[${r.file_path}]\n${r.content}`).join('\n\n');
    const response = await fetch('http://127.0.0.1:11434/api/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            model: 'command-r',
            stream: false,
            system: 'Je filtert de gegeven KB-context. Behoud alleen fragmenten die direct relevant zijn voor de gebruikersvraag. Output alleen die gefilterde inhoud, geen uitleg.',
            prompt: `Vraag: ${instruction}\n\nContext:\n${contextBlob}\n\nGefilterde relevante inhoud:`,
            options: { num_ctx: 24576, temperature: 0.2 },
        }),
    });
    if (!response.ok) throw new Error(`Ollama: ${response.status}`);
    const data = await response.json();
    return data.response || '';
}

// STAP 3: De Mastermind (Claude) – alleen gefilterde context
async function callClaudeWithFilteredContext(instruction, filteredContext) {
    const msg = await anthropic.messages.create({
        model: 'claude-3-5-sonnet-20241022',
        max_tokens: 4096,
        system: 'Je bent de HavunCore Mastermind. Gebruik de GEFILTERDE context om de vraag te beantwoorden of code-wijzigingen voor te stellen.',
        messages: [
            {
                role: 'user',
                content: `Context uit lokale KB:\n${filteredContext}\n\nVraag: ${instruction}`,
            },
        ],
    });
    return msg.content[0].text;
}

router.post('/intelligent', async (req, res) => {
    const { instruction, project } = req.body;

    if (!instruction) {
        return res.status(400).json({ error: 'instruction is verplicht' });
    }

    const db = req.app.locals.db; // of waar je SQLite-connectie vandaan komt
    if (!db) {
        return res.status(500).json({ error: 'Database niet beschikbaar' });
    }

    try {
        // STAP 1: Lokaal zoeken (Ranking)
        const rawContext = await getRawContext(db, project || null);

        // STAP 2: Lokaal filteren met Command-R (64GB RAM power)
        const filteredContext = await callOllamaFilter(instruction, rawContext);

        // STAP 3: De 'Mastermind' (Claude) aanroepen
        const finalResponse = await callClaudeWithFilteredContext(instruction, filteredContext);

        res.json({
            success: true,
            answer: finalResponse,
            sourceMetadata: 'Gefilterd door Command-R (Local)',
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

module.exports = router;
