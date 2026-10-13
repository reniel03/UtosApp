const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';

const getTaskKey = (title) => String(title || '')
    .trim()
    .replace(/[.#$[\]/]/g, '-')
    .replace(/\s+/g, ' ')
    .slice(0, 120);

const response = await fetch(`${FIREBASE_DB_URL}/tasks.json`);
if (!response.ok) throw new Error(`Could not read tasks: ${response.status}`);

const tasks = await response.json() || {};
for (const [oldKey, task] of Object.entries(tasks)) {
    const newKey = getTaskKey(task?.title);
    if (!newKey || oldKey === newKey) continue;

    const writeResponse = await fetch(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(newKey)}.json`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(task)
    });
    if (!writeResponse.ok) throw new Error(`Could not write ${newKey}: ${writeResponse.status}`);

    const deleteResponse = await fetch(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(oldKey)}.json`, { method: 'DELETE' });
    if (!deleteResponse.ok) throw new Error(`Could not remove ${oldKey}: ${deleteResponse.status}`);
    console.log(`${oldKey} -> ${newKey}`);
}

console.log('Task key migration complete.');
