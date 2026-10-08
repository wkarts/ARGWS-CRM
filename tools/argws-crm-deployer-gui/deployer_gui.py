from __future__ import annotations

import argparse
import os
import subprocess
import sys
import tempfile
import threading
import tkinter as tk
from pathlib import Path
from tkinter import filedialog, messagebox, ttk

WINDOWS_CLI = "argws-crm-deployer-win-x64.exe"
LINUX_CLI = "argws-crm-deployer-linux-x64"


def application_directory() -> Path:
    return Path(sys.executable).absolute().parent if getattr(sys, "frozen", False) else Path(__file__).resolve().parent


def find_cli(explicit: str | None = None) -> Path | None:
    if explicit:
        candidate = Path(explicit).expanduser().absolute()
        return candidate if candidate.is_file() else None
    name = WINDOWS_CLI if os.name == "nt" else LINUX_CLI
    candidates = [application_directory() / name, Path(__file__).resolve().parent / name]
    if not getattr(sys, "frozen", False):
        root = Path(__file__).resolve().parents[2]
        target = "x86_64-pc-windows-msvc" if os.name == "nt" else "x86_64-unknown-linux-musl"
        binary = "argws-crm-deployer.exe" if os.name == "nt" else "argws-crm-deployer"
        candidates.extend([
            root / "tools" / "argws-crm-deployer" / "target" / "release" / name,
            root / "tools" / "argws-crm-deployer" / "target" / target / "release" / binary,
        ])
    return next((path for path in candidates if path.is_file()), None)


def run_backend(cli: Path, arguments: list[str]) -> tuple[int, str]:
    result = subprocess.run(
        [str(cli), *arguments],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=180,
        creationflags=subprocess.CREATE_NO_WINDOW if os.name == "nt" else 0,
        check=False,
    )
    return result.returncode, (result.stdout or result.stderr).strip()


def smoke_test(cli: Path) -> int:
    code, version = run_backend(cli, ["version"])
    if code != 0 or not version:
        print("O backend CLI não informou uma versão.", file=sys.stderr)
        return 1
    with tempfile.TemporaryDirectory(prefix="argws-deployer-gui-smoke-") as temporary:
        output = Path(temporary) / "stack"
        code, message = run_backend(cli, [
            "generate", "--environment", "production", "--database", "mariadb",
            "--output", str(output),
        ])
        if code != 0:
            print(message or "O CLI não conseguiu gerar a stack.", file=sys.stderr)
            return 1
        code, message = run_backend(cli, ["validate", "--directory", str(output)])
        if code != 0 or not (output / ".env").is_file() or not (output / "compose.yaml").is_file():
            print(message or "A validação da stack gerada falhou.", file=sys.stderr)
            return 1
    return 0


def report_gui_failure(error: Exception) -> int:
    diagnostic = str(error).splitlines()[0][:400] or type(error).__name__
    path = Path(tempfile.gettempdir()) / "argws-crm-deployer-gui.log"
    try:
        with path.open("a", encoding="utf-8") as stream:
            stream.write(f"startup=failure diagnostic={diagnostic}\n")
        if os.name != "nt":
            path.chmod(0o600)
    except OSError:
        path = Path("(não foi possível gravar o diagnóstico)")

    executable = WINDOWS_CLI if os.name == "nt" else LINUX_CLI
    message = (
        "A interface gráfica não conseguiu iniciar. Nenhuma implantação foi executada.\n\n"
        f"Diagnóstico: {diagnostic}\n\n"
        f"Use o CLI no terminal: {executable} interactive\n"
        f"Diagnóstico: {path}"
    )
    if os.name == "nt":
        try:
            import ctypes
            ctypes.windll.user32.MessageBoxW(0, message, "ARGWS CRM Deployer", 0x10)
        except Exception:
            print(message, file=sys.stderr)
    else:
        print(message, file=sys.stderr)
    return 3


def ui_smoke_test() -> int:
    try:
        root = tk.Tk()
        root.title("ARGWS CRM Deployer")
        ttk.Label(root, text="Teste automatizado da interface").pack(padx=24, pady=20)
        root.after(300, root.destroy)
        root.mainloop()
        return 0
    except tk.TclError as error:
        print(f"Não foi possível inicializar a interface Tk: {error}", file=sys.stderr)
        return 1


class DeployerWindow:
    def __init__(self, root: tk.Tk, cli: Path | None):
        self.root = root
        self.cli = cli
        self.busy = False
        self.environment = tk.StringVar(value="Produção")
        self.database = tk.StringVar(value="MySQL 8.0")
        self.version = tk.StringVar(value="")
        self.output = tk.StringVar(value=str(Path.home() / "argws-crm-deploy"))
        self.force = tk.BooleanVar(value=False)
        self.status = tk.StringVar(value="")
        self.version_entry: ttk.Entry | None = None
        root.title("ARGWS CRM Deployer")
        root.geometry("760x450")
        root.minsize(620, 400)
        self._build()
        if self.cli is None:
            self.status.set("CLI não encontrado. Extraia o ZIP gráfico inteiro para manter o executável ao lado.")
        else:
            try:
                code, detected = run_backend(self.cli, ["version"])
                self.version.set(detected if code == 0 else "")
                self.status.set("Pronto para gerar ou validar a stack." if code == 0 else detected)
            except Exception as error:
                self.status.set(f"Não foi possível iniciar o CLI: {error}")
        self._refresh_version_state()

    def _build(self) -> None:
        frame = ttk.Frame(self.root, padding=22)
        frame.pack(fill="both", expand=True)
        frame.columnconfigure(1, weight=1)
        ttk.Label(frame, text="ARGWS CRM", font=("Segoe UI", 18, "bold")).grid(row=0, column=0, columnspan=3, sticky="w")
        ttk.Label(frame, text="Preparar arquivos de implantação").grid(row=1, column=0, columnspan=3, sticky="w", pady=(0, 20))
        ttk.Label(frame, text="Ambiente").grid(row=2, column=0, sticky="w", pady=6)
        self.environment_box = ttk.Combobox(frame, textvariable=self.environment, values=("Desenvolvimento", "Produção"), state="readonly", width=24)
        self.environment_box.grid(row=2, column=1, sticky="w", pady=6)
        self.environment_box.bind("<<ComboboxSelected>>", lambda _event: self._refresh_version_state())
        ttk.Label(frame, text="Banco de dados").grid(row=3, column=0, sticky="w", pady=6)
        ttk.Combobox(frame, textvariable=self.database, values=("MySQL 8.0", "MariaDB 11.4"), state="readonly", width=24).grid(row=3, column=1, sticky="w", pady=6)
        ttk.Label(frame, text="Versão da imagem").grid(row=4, column=0, sticky="w", pady=6)
        self.version_entry = ttk.Entry(frame, textvariable=self.version, width=28)
        self.version_entry.grid(row=4, column=1, sticky="w", pady=6)
        ttk.Label(frame, text="Pasta de destino").grid(row=5, column=0, sticky="w", pady=6)
        ttk.Entry(frame, textvariable=self.output).grid(row=5, column=1, sticky="ew", pady=6)
        ttk.Button(frame, text="Procurar...", command=self._browse).grid(row=5, column=2, padx=(8, 0), pady=6)
        ttk.Checkbutton(frame, text="Substituir compose.yaml quando diferir do modelo", variable=self.force).grid(row=6, column=1, columnspan=2, sticky="w", pady=(10, 18))
        actions = ttk.Frame(frame)
        actions.grid(row=7, column=0, columnspan=3, sticky="w")
        self.generate_button = ttk.Button(actions, text="Gerar compose.yaml e .env", command=self._generate)
        self.generate_button.pack(side="left")
        self.validate_button = ttk.Button(actions, text="Validar arquivos existentes", command=self._validate)
        self.validate_button.pack(side="left", padx=(10, 0))
        ttk.Button(actions, text="Abrir pasta", command=self._open_folder).pack(side="left", padx=(10, 0))
        ttk.Separator(frame).grid(row=8, column=0, columnspan=3, sticky="ew", pady=18)
        ttk.Label(frame, textvariable=self.status, wraplength=690).grid(row=9, column=0, columnspan=3, sticky="w")
        ttk.Label(frame, text="O primeiro administrador é criado pelo assistente web /setup. O .env e as senhas existentes são preservados.", wraplength=690).grid(row=10, column=0, columnspan=3, sticky="w", pady=(14, 0))

    def _refresh_version_state(self) -> None:
        if self.version_entry is not None:
            self.version_entry.configure(state="normal" if self.environment.get() == "Produção" else "disabled")

    def _browse(self) -> None:
        initial = self.output.get().strip()
        if not Path(initial).is_dir():
            initial = str(Path.home())
        selected = filedialog.askdirectory(title="Escolher pasta de destino", initialdir=initial)
        if selected:
            self.output.set(selected)

    def _set_busy(self, busy: bool) -> None:
        self.busy = busy
        state = "disabled" if busy else "normal"
        self.generate_button.configure(state=state)
        self.validate_button.configure(state=state)

    def _run(self, arguments: list[str], success_text: str) -> None:
        if self.busy:
            return
        if self.cli is None:
            messagebox.showerror("Executável CLI ausente", "Extraia o ZIP completo para manter o CLI ao lado da GUI.", parent=self.root)
            return
        self._set_busy(True)
        self.status.set("Executando e validando os arquivos...")
        def worker() -> None:
            try:
                code, output = run_backend(self.cli, arguments)
            except Exception as error:
                code, output = 1, str(error)
            def finish() -> None:
                self._set_busy(False)
                if code == 0:
                    self.status.set(output or success_text)
                else:
                    self.status.set(output or "A operação falhou.")
                    messagebox.showerror("Falha no deployer", output or "A operação falhou.", parent=self.root)
            self.root.after(0, finish)
        threading.Thread(target=worker, daemon=True).start()

    def _generate(self) -> None:
        destination = self.output.get().strip()
        if not destination:
            messagebox.showerror("Destino obrigatório", "Informe a pasta de destino.", parent=self.root)
            return
        environment = "develop" if self.environment.get() == "Desenvolvimento" else "production"
        database = "mariadb" if self.database.get() == "MariaDB 11.4" else "mysql"
        command = ["generate", "--environment", environment, "--database", database, "--output", destination]
        if environment == "production" and self.version.get().strip():
            command.extend(["--version", self.version.get().strip()])
        if self.force.get():
            command.append("--force")
        self._run(command, f"Stack preparada e validada em {destination}.")

    def _validate(self) -> None:
        destination = self.output.get().strip()
        if not destination:
            messagebox.showerror("Destino obrigatório", "Informe a pasta que contém os arquivos.", parent=self.root)
            return
        self._run(["validate", "--directory", destination], "Stack válida.")

    def _open_folder(self) -> None:
        destination = Path(self.output.get().strip()).expanduser()
        if not destination.is_dir():
            messagebox.showerror("Pasta inexistente", "Gere ou informe uma pasta existente.", parent=self.root)
            return
        try:
            if os.name == "nt":
                os.startfile(str(destination))
            elif sys.platform == "darwin":
                subprocess.Popen(["open", str(destination)])
            else:
                subprocess.Popen(["xdg-open", str(destination)])
        except OSError as error:
            messagebox.showerror("Falha ao abrir a pasta", str(error), parent=self.root)


def main() -> int:
    parser = argparse.ArgumentParser(add_help=True)
    parser.add_argument("--cli", help=argparse.SUPPRESS)
    parser.add_argument("--smoke-test", action="store_true", help=argparse.SUPPRESS)
    parser.add_argument("--ui-smoke-test", action="store_true", help=argparse.SUPPRESS)
    arguments = parser.parse_args()
    cli = find_cli(arguments.cli)
    if arguments.smoke_test:
        if cli is None:
            print("O executável CLI não está ao lado da interface.", file=sys.stderr)
            return 1
        return smoke_test(cli)
    if arguments.ui_smoke_test:
        return ui_smoke_test()
    try:
        root = tk.Tk()
        DeployerWindow(root, cli)
        root.mainloop()
        return 0
    except Exception as error:
        return report_gui_failure(error)


if __name__ == "__main__":
    raise SystemExit(main())
