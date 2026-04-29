#!/usr/bin/env python3

import argparse
import base64
import json
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request
from pathlib import Path

import websocket


def parse_args():
    parser = argparse.ArgumentParser(description="Renderiza PDF de contrato via Chromium DevTools.")
    parser.add_argument("--chromium", required=True)
    parser.add_argument("--html", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--paper-width", type=float, required=True)
    parser.add_argument("--paper-height", type=float, required=True)
    parser.add_argument("--margin-top", type=float, required=True)
    parser.add_argument("--margin-right", type=float, required=True)
    parser.add_argument("--margin-bottom", type=float, required=True)
    parser.add_argument("--margin-left", type=float, required=True)
    parser.add_argument("--footer-template-file")
    return parser.parse_args()


def free_port():
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.bind(("127.0.0.1", 0))
    port = sock.getsockname()[1]
    sock.close()
    return port


def wait_json(port, path, timeout=20):
    deadline = time.time() + timeout
    url = f"http://127.0.0.1:{port}{path}"
    last_error = None

    while time.time() < deadline:
        try:
            with urllib.request.urlopen(url, timeout=2) as response:
                return json.loads(response.read().decode("utf-8"))
        except Exception as exc:  # noqa: BLE001
            last_error = exc
            time.sleep(0.2)

    raise RuntimeError(f"Nao foi possivel conectar ao Chromium DevTools em {url}: {last_error}")


class CdpClient:
    def __init__(self, websocket_url):
        self.socket = websocket.create_connection(websocket_url, timeout=30)
        self.message_id = 0

    def close(self):
        try:
            self.socket.close()
        except Exception:  # noqa: BLE001
            pass

    def send(self, method, params=None, timeout=30):
        self.message_id += 1
        current_id = self.message_id
        self.socket.send(json.dumps({
            "id": current_id,
            "method": method,
            "params": params or {},
        }))

        deadline = time.time() + timeout
        while time.time() < deadline:
            message = json.loads(self.socket.recv())
            if message.get("id") == current_id:
                if "error" in message:
                    raise RuntimeError(f"Erro DevTools em {method}: {message['error']}")
                return message.get("result", {})

        raise TimeoutError(f"Tempo esgotado aguardando resposta do comando {method}.")

    def wait_for_event(self, method, timeout=30):
        deadline = time.time() + timeout
        while time.time() < deadline:
            message = json.loads(self.socket.recv())
            if message.get("method") == method:
                return message.get("params", {})

        raise TimeoutError(f"Tempo esgotado aguardando evento {method}.")


def footer_template(path):
    if not path:
        return "<div></div>"

    footer_path = Path(path)
    if not footer_path.is_file():
        return "<div></div>"

    content = footer_path.read_text(encoding="utf-8").strip()
    return content if content else "<div></div>"


def main():
    args = parse_args()
    port = free_port()
    profile_dir = tempfile.mkdtemp(prefix="ancora-contract-pdf-")
    chrome = None
    client = None

    try:
        chrome = subprocess.Popen(
            [
                args.chromium,
                "--headless",
                "--no-sandbox",
                "--disable-gpu",
                "--disable-dev-shm-usage",
                "--disable-extensions",
                "--no-first-run",
                "--no-default-browser-check",
                "--allow-file-access-from-files",
                f"--remote-debugging-port={port}",
                f"--user-data-dir={profile_dir}",
                "about:blank",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

        targets = wait_json(port, "/json/list")
        page_target = next((item for item in targets if item.get("type") == "page" and item.get("webSocketDebuggerUrl")), None)
        if not page_target:
            raise RuntimeError("Chromium iniciou, mas nao foi encontrado alvo de pagina para gerar o PDF.")

        client = CdpClient(page_target["webSocketDebuggerUrl"])
        client.send("Page.enable")
        client.send("Runtime.enable")
        client.send("Emulation.setEmulatedMedia", {"media": "print"})

        client.send("Page.navigate", {"url": Path(args.html).resolve().as_uri()})
        client.wait_for_event("Page.loadEventFired", timeout=45)
        client.send(
            "Runtime.evaluate",
            {
                "expression": """
                    new Promise((resolve) => {
                        const images = Array.from(document.images || []);
                        const imagePromises = images.map((image) => {
                            if (image.complete) return Promise.resolve(true);
                            return new Promise((done) => {
                                image.onload = () => done(true);
                                image.onerror = () => done(true);
                            });
                        });

                        Promise.all(imagePromises).then(() => {
                            if (document.fonts && document.fonts.ready) {
                                document.fonts.ready.then(() => setTimeout(() => resolve(true), 250));
                                return;
                            }

                            setTimeout(() => resolve(true), 250);
                        });
                    });
                """,
                "awaitPromise": True,
                "returnByValue": True,
            },
            timeout=60,
        )

        result = client.send(
            "Page.printToPDF",
            {
                "printBackground": True,
                "displayHeaderFooter": True,
                "headerTemplate": "<div></div>",
                "footerTemplate": footer_template(args.footer_template_file),
                "paperWidth": args.paper_width,
                "paperHeight": args.paper_height,
                "marginTop": args.margin_top,
                "marginRight": args.margin_right,
                "marginBottom": args.margin_bottom,
                "marginLeft": args.margin_left,
                "preferCSSPageSize": False,
            },
            timeout=120,
        )

        pdf_data = result.get("data")
        if not pdf_data:
            raise RuntimeError("Chromium nao retornou o conteudo do PDF.")

        output_path = Path(args.output)
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_bytes(base64.b64decode(pdf_data))
        return 0
    except Exception as exc:  # noqa: BLE001
        print(str(exc), file=sys.stderr)
        return 1
    finally:
        if client:
            client.close()

        if chrome:
            try:
                chrome.terminate()
                chrome.wait(timeout=5)
            except Exception:  # noqa: BLE001
                try:
                    chrome.kill()
                except Exception:  # noqa: BLE001
                    pass

        shutil.rmtree(profile_dir, ignore_errors=True)


if __name__ == "__main__":
    sys.exit(main())
