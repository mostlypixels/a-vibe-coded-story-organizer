/**
 * Test bootstrap: give every test file a working `localStorage`.
 *
 * From Node 24 on, Node defines a `localStorage` global of its own. It hides
 * the one jsdom installs, and it is not a real Storage: it has no `clear()`
 * and no `length`. Tests that assert the app stores nothing then fail on the
 * environment, not on the code. Node 20 has no such global and is unaffected,
 * which is why this only appears on newer machines.
 *
 * Installing a small in-memory Storage keeps the suite identical on every Node
 * version. No application code uses Web Storage, so nothing depends on the
 * richer jsdom behaviour.
 */
class MemoryStorage {
    #items = new Map();

    get length() {
        return this.#items.size;
    }

    key(index) {
        return [...this.#items.keys()][index] ?? null;
    }

    getItem(key) {
        return this.#items.has(String(key)) ? this.#items.get(String(key)) : null;
    }

    setItem(key, value) {
        this.#items.set(String(key), String(value));
    }

    removeItem(key) {
        this.#items.delete(String(key));
    }

    clear() {
        this.#items.clear();
    }
}

for (const name of ['localStorage', 'sessionStorage']) {
    const storage = new MemoryStorage();

    Object.defineProperty(globalThis, name, {
        value: storage,
        configurable: true,
        writable: true,
    });

    if (globalThis.window && globalThis.window !== globalThis) {
        Object.defineProperty(globalThis.window, name, {
            value: storage,
            configurable: true,
            writable: true,
        });
    }
}
