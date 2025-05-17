class HotModule {
    file;
    cb;

    constructor(file) {
        this.file = file;
    }

    accept(cb) {
        this.cb = cb;
    }

    handleAccept() {
        if (!this.cb) {
            return;
        }

        import(`${this.file}?t=${Date.now()}`).then((newMod) => {
            this.cb(newMod);
        });
    }
}

function hmrClient(mod) {
    const url = new URL(mod.url);
    const hot = new HotModule(url.pathname);
    import.meta.hot = hot;
    window.hotModules.set(url.pathname, hot);
}

/** @type {Map<string, HotModule>} */
window.hotModules ??= new Map();

window.eventSource;

if (!window.eventSource) {

    const importMapElement = document.querySelector('script[type="importmap"]');
    const importMapJson = JSON.parse(importMapElement.textContent);

    const eventSource = new EventSource('http://localhost/.well-known/mercure?topic=symfony-hmr');
    eventSource.onmessage = event => {
        const data = JSON.parse(event.data);

        console.log(data);
        if (importMapJson.imports.hasOwnProperty(data.filePath)) {
            console.log('Hot module replacement - found it');
        } else {
            console.log('Hot module replacement - not found, reloading the page to get latest importmap');
            location.reload();
        }
    };

    // const ws = new window.WebSocket("ws://localhost:8080");
    //
    // ws.addEventListener("message", (msg) => {
    //     const data = JSON.parse(msg.data);
    //     const mod = window.hotModules.get(data.file);
    //     console.log(data.file);
    //     mod.handleAccept();
    // });

    window.eventSource = eventSource;
}

hmrClient(import.meta);
