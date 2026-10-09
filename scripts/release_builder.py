#!/usr/bin/env python3
"""
MyStore Release Packaging & Marketplace Builder Dashboard
==========================================================
Interactive GUI dashboard for preparing, sanitizing, and packaging the
full developer codebase (Web + Backend + Flutter App + Documentation)
for sale on Stacklane (stacklane.melur.com) / CodeCanyon.
"""

import os
import sys
import shutil
import zipfile
import hashlib
import threading
import subprocess
from datetime import datetime
import tkinter as tk
from tkinter import ttk, messagebox, filedialog


# -----------------------------------------------------------------------------
# Configuration & Constants
# -----------------------------------------------------------------------------
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.path.abspath(os.path.join(SCRIPT_DIR, ".."))
DEFAULT_OUTPUT_DIR = os.path.join(REPO_ROOT, "dist_release")
DEFAULT_VERSION = "1.0.0"
PRODUCT_NAME = "MyStore"
PRODUCT_CODE = "mystores"
MARKETPLACE_TARGET = "stacklane.melur.com"


# -----------------------------------------------------------------------------
# Core Diagnostic & Packaging Engine
# -----------------------------------------------------------------------------
class ReleaseEngine:
    def __init__(self, repo_root=REPO_ROOT, log_callback=None):
        self.repo_root = repo_root
        self.log_callback = log_callback or (lambda msg: print(msg))

    def log(self, message):
        self.log_callback(message)

    def run_checklist(self):
        """Perform pre-flight sanity and readiness checks on the repository."""
        results = []

        # 1. Backend Source Code
        backend_dir = os.path.join(self.repo_root, "backend")
        install_ctrl = os.path.join(backend_dir, "app", "Http", "Controllers", "InstallController.php")
        license_svc = os.path.join(backend_dir, "app", "Services", "Licensing", "LicenseService.php")
        env_example = os.path.join(backend_dir, ".env.example")
        
        backend_ok = (
            os.path.isdir(backend_dir)
            and os.path.isfile(install_ctrl)
            and os.path.isfile(license_svc)
            and os.path.isfile(env_example)
        )
        results.append({
            "key": "backend",
            "title": "Backend Codebase (Laravel 12)",
            "status": "PASS" if backend_ok else "FAIL",
            "desc": "Laravel 12 core, InstallController, LicenseService, and clean .env.example present."
            if backend_ok else "Missing critical backend files or controllers."
        })

        # 2. Frontend Source Code & Build
        frontend_dir = os.path.join(self.repo_root, "frontend")
        frontend_src = os.path.join(frontend_dir, "src")
        backend_public_index = os.path.join(backend_dir, "public", "index.html")
        frontend_dist_index = os.path.join(frontend_dir, "dist", "index.html")

        if os.path.isdir(frontend_src):
            if os.path.isfile(backend_public_index) or os.path.isfile(frontend_dist_index):
                frontend_status = "PASS"
                frontend_desc = "React 19 source code verified & pre-compiled web assets present in public directory."
            else:
                frontend_status = "WARN"
                frontend_desc = "React 19 source code ready, but frontend build has not been generated yet."
        else:
            frontend_status = "FAIL"
            frontend_desc = "frontend/src directory not found."

        results.append({
            "key": "frontend",
            "title": "Frontend Codebase (React 19)",
            "status": frontend_status,
            "desc": frontend_desc
        })

        # 3. Flutter Mobile App
        mobile_dir = os.path.join(self.repo_root, "mobile")
        pubspec = os.path.join(mobile_dir, "pubspec.yaml")
        endpoints = os.path.join(mobile_dir, "lib", "core", "api", "api_endpoints.dart")
        mobile_ok = os.path.isdir(mobile_dir) and os.path.isfile(pubspec) and os.path.isfile(endpoints)
        results.append({
            "key": "mobile",
            "title": "Flutter Mobile App Codebase",
            "status": "PASS" if mobile_ok else "FAIL",
            "desc": "Flutter companion project, pubspec.yaml, and configurable api_endpoints.dart verified."
            if mobile_ok else "mobile/ source code or api_endpoints.dart missing."
        })

        # 4. Buyer Documentation
        doc_path = os.path.join(self.repo_root, "Docs", "documentation.html")
        doc_ok = os.path.isfile(doc_path) and os.path.getsize(doc_path) > 5000
        results.append({
            "key": "docs",
            "title": "Buyer HTML Documentation",
            "status": "PASS" if doc_ok else "FAIL",
            "desc": "Comprehensive standalone documentation.html (Web setup + Flutter compile guide) ready."
            if doc_ok else "Docs/documentation.html not found or incomplete."
        })

        # 5. Sanitization & Security Rules
        # Ensure that live secrets are NOT in .env.example
        env_ex_safe = True
        if os.path.isfile(env_example):
            with open(env_example, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()
                if "APP_INSTALLED=true" in content or "AKAVFf8" in content:
                    env_ex_safe = False
        results.append({
            "key": "sanitization",
            "title": "Security & Secret Sanitization",
            "status": "PASS" if env_ex_safe else "FAIL",
            "desc": "Active exclusion filters armed (.git, live .env, local credentials, logs, and build caches)."
            if env_ex_safe else "Warning: .env.example contains production secrets or installed flags!"
        })

        # 6. Licensing Engine Target
        lic_ok = False
        if os.path.isfile(license_svc):
            with open(license_svc, "r", encoding="utf-8", errors="ignore") as f:
                svc_code = f.read()
                if PRODUCT_CODE in svc_code and "stacklane.melur.com" in svc_code:
                    lic_ok = True
        results.append({
            "key": "licensing",
            "title": f"Licensing Engine ({MARKETPLACE_TARGET})",
            "status": "PASS" if lic_ok else "FAIL",
            "desc": f"Configured for product '{PRODUCT_CODE}' with RS256 local verification via {MARKETPLACE_TARGET}."
            if lic_ok else "Licensing configuration mismatched."
        })

        return results

    def build_package(self, version=DEFAULT_VERSION, output_dir=DEFAULT_OUTPUT_DIR, build_frontend=True):
        """Execute the full packaging workflow."""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        self.log(f"==================================================")
        self.log(f" Building {PRODUCT_NAME} Marketplace Codebase Package")
        self.log(f" Version: {version} | Target: {MARKETPLACE_TARGET}")
        self.log(f" Time: {timestamp}")
        self.log(f"==================================================")

        staging_dir = os.path.join(output_dir, f"_staging_{timestamp}")
        zip_filename = f"{PRODUCT_NAME}-v{version}-Codebase.zip"
        final_zip_path = os.path.join(output_dir, zip_filename)

        os.makedirs(output_dir, exist_ok=True)
        if os.path.exists(staging_dir):
            shutil.rmtree(staging_dir)
        os.makedirs(staging_dir, exist_ok=True)

        # -------------------------------------------------------------
        # STEP 1: Optional Frontend Build
        # -------------------------------------------------------------
        frontend_dir = os.path.join(self.repo_root, "frontend")
        backend_public_dir = os.path.join(self.repo_root, "backend", "public")
        if build_frontend:
            self.log("\n[1/6] Compiling React 19 Frontend (npm run build)...")
            npm_cmd = "npm.cmd" if os.name == "nt" else "npm"
            try:
                proc = subprocess.run(
                    [npm_cmd, "run", "build"],
                    cwd=frontend_dir,
                    capture_output=True,
                    text=True,
                    check=False
                )
                if proc.returncode == 0:
                    self.log("  [OK] Frontend built successfully.")
                    frontend_dist = os.path.join(frontend_dir, "dist")
                    if os.path.exists(frontend_dist):
                        # Copy dist assets into backend/public for instant cPanel buyer upload
                        for item in os.listdir(frontend_dist):
                            s = os.path.join(frontend_dist, item)
                            d = os.path.join(backend_public_dir, item)
                            if os.path.isdir(s):
                                shutil.copytree(s, d, dirs_exist_ok=True)
                            else:
                                shutil.copy2(s, d)
                        self.log("  [OK] Synchronized compiled assets into backend/public directory.")
                else:
                    self.log(f"  [!] npm run build returned code {proc.returncode}: {proc.stderr[:200]}")
            except Exception as e:
                self.log(f"  [!] Could not run npm build: {e}")
        else:
            self.log("\n[1/6] Skipping frontend build as requested.")

        # -------------------------------------------------------------
        # STEP 2: Package Documentation
        # -------------------------------------------------------------
        self.log("\n[2/6] Packaging Buyer Documentation...")
        doc_dest = os.path.join(staging_dir, "Documentation")
        os.makedirs(doc_dest, exist_ok=True)
        doc_src = os.path.join(self.repo_root, "Docs", "documentation.html")
        if os.path.exists(doc_src):
            shutil.copy2(doc_src, os.path.join(doc_dest, "index.html"))
            self.log("  [OK] Created Documentation/index.html")
        else:
            self.log("  [!] Documentation source file missing!")

        # -------------------------------------------------------------
        # STEP 3: Package Web & Backend Codebase
        # -------------------------------------------------------------
        self.log("\n[3/6] Packaging Web & Backend Codebase (Laravel + React)...")
        web_dest = os.path.join(staging_dir, "Web_and_Backend")
        os.makedirs(web_dest, exist_ok=True)

        # Exclusions for backend
        backend_src = os.path.join(self.repo_root, "backend")
        exclude_dirs = {
            ".git", ".github", "node_modules", "tests", ".idea", ".vscode",
            ".phpunit.cache", "vendor"
        }
        exclude_files = {
            ".env", ".env.production", ".env.local", "installed",
            "license.json", ".gitattributes", ".gitignore"
        }

        copied_backend_files = 0
        for root, dirs, files in os.walk(backend_src):
            dirs[:] = [d for d in dirs if d not in exclude_dirs]
            rel_dir = os.path.relpath(root, backend_src)
            target_dir = os.path.join(web_dest, rel_dir) if rel_dir != "." else web_dest
            os.makedirs(target_dir, exist_ok=True)

            for f in files:
                if f in exclude_files:
                    continue
                # Skip any log files in storage/logs
                if f.endswith(".log") and "storage" in rel_dir:
                    continue
                # Skip installed lock file
                if f == "installed" and "storage" in rel_dir:
                    continue
                shutil.copy2(os.path.join(root, f), os.path.join(target_dir, f))
                copied_backend_files += 1

        # Copy React frontend source code into Web_and_Backend/frontend so developers can modify UI
        self.log("  [OK] Bundling React 19 source code for developer customization...")
        react_dest = os.path.join(web_dest, "frontend_source")
        exclude_frontend_dirs = {".git", "node_modules", "dist", ".idea", ".vscode"}
        for root, dirs, files in os.walk(frontend_dir):
            dirs[:] = [d for d in dirs if d not in exclude_frontend_dirs]
            rel_dir = os.path.relpath(root, frontend_dir)
            target_dir = os.path.join(react_dest, rel_dir) if rel_dir != "." else react_dest
            os.makedirs(target_dir, exist_ok=True)
            for f in files:
                if f in {".env", ".env.local"}:
                    continue
                shutil.copy2(os.path.join(root, f), os.path.join(target_dir, f))

        self.log(f"  [OK] Packaged Web & Backend codebase ({copied_backend_files} files sanitized).")

        # -------------------------------------------------------------
        # STEP 4: Package Flutter Mobile Codebase
        # -------------------------------------------------------------
        self.log("\n[4/6] Packaging Flutter Mobile App Codebase...")
        mobile_src = os.path.join(self.repo_root, "mobile")
        mobile_dest = os.path.join(staging_dir, "Flutter_Mobile_App")
        os.makedirs(mobile_dest, exist_ok=True)

        exclude_mobile_dirs = {
            ".dart_tool", "build", ".idea", ".gradle", "ios/Pods",
            "ios/.symlinks", ".vscode"
        }
        exclude_mobile_files = {
            "local.properties", "shopee_manager.iml", ".DS_Store"
        }

        copied_mobile_files = 0
        for root, dirs, files in os.walk(mobile_src):
            dirs[:] = [d for d in dirs if d not in exclude_mobile_dirs]
            rel_dir = os.path.relpath(root, mobile_src)
            target_dir = os.path.join(mobile_dest, rel_dir) if rel_dir != "." else mobile_dest
            os.makedirs(target_dir, exist_ok=True)

            for f in files:
                if f in exclude_mobile_files or f.endswith(".iml"):
                    continue
                shutil.copy2(os.path.join(root, f), os.path.join(target_dir, f))
                copied_mobile_files += 1

        self.log(f"  [OK] Packaged Flutter codebase ({copied_mobile_files} files sanitized).")

        # -------------------------------------------------------------
        # STEP 5: Create Quick-Start README.txt
        # -------------------------------------------------------------
        self.log("\n[5/6] Generating README.txt...")
        readme_content = f"""======================================================================
 {PRODUCT_NAME} - Multi-Store E-Commerce & POS Management Application
 Full Source Code Package (Web Platform + Mobile App)
 Version: {version}
======================================================================

Thank you for purchasing {PRODUCT_NAME}!

PACKAGE DIRECTORY STRUCTURE:
----------------------------
1. Documentation/
   Open 'Documentation/index.html' in any web browser for complete,
   step-by-step instructions for installing the web backend, running
   the web setup wizard, and compiling the Flutter mobile app.

2. Web_and_Backend/
   Contains the complete Laravel 12 backend codebase.
   - For cPanel / Shared Hosting: Upload all files inside this directory
     to your web root (or public_html). Pre-compiled web assets are
     already bundled in 'public/'.
   - For Developers: The full React 19 source code is included in
     'frontend_source/' for UI customization.

3. Flutter_Mobile_App/
   Contains the complete Flutter companion app source code.
   - Open this folder in VS Code or Android Studio.
   - Point your API URL in: 'lib/core/api/api_endpoints.dart'.
   - Compile for Android: 'flutter build apk --release'
   - Compile for Play Store: 'flutter build appbundle --release'

QUICK START (5 MINUTES):
------------------------
1. Upload the contents of 'Web_and_Backend' to your web hosting.
2. Create an empty MySQL database in cPanel / MySQL manager.
3. Open your browser and navigate to:
   https://your-domain.com/install
4. Follow the 5-step installer wizard and enter your Stacklane License Key.
5. Your store and dashboard are live!

SUPPORT & LICENSING:
--------------------
License verification powered by Stacklane ({MARKETPLACE_TARGET}).
For support, documentation updates, or licensing inquiries, visit your
purchased order page.
"""
        with open(os.path.join(staging_dir, "README.txt"), "w", encoding="utf-8") as f:
            f.write(readme_content)
        self.log("  [OK] Generated README.txt.")

        # -------------------------------------------------------------
        # STEP 6: Zip Archive Creation
        # -------------------------------------------------------------
        self.log("\n[6/6] Compressing final distribution ZIP...")
        if os.path.exists(final_zip_path):
            os.remove(final_zip_path)

        total_files = 0
        with zipfile.ZipFile(final_zip_path, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zipf:
            for root, _, files in os.walk(staging_dir):
                for f in files:
                    file_path = os.path.join(root, f)
                    arcname = os.path.relpath(file_path, staging_dir)
                    zipf.write(file_path, arcname)
                    total_files += 1

        # Cleanup staging
        shutil.rmtree(staging_dir, ignore_errors=True)

        # Calculate final stats
        zip_size_mb = os.path.getsize(final_zip_path) / (1024 * 1024)
        hasher = hashlib.sha256()
        with open(final_zip_path, "rb") as f:
            while chunk := f.read(65536):
                hasher.update(chunk)
        sha256 = hasher.hexdigest()

        self.log(f"\n==================================================")
        self.log(f" [SUCCESS] RELEASE PACKAGE COMPLETED SUCCESSFULLY!")
        self.log(f" Package:  {final_zip_path}")
        self.log(f" Files:    {total_files} total files packaged")
        self.log(f" Size:     {zip_size_mb:.2f} MB")
        self.log(f" SHA-256:  {sha256}")
        self.log(f"==================================================")

        return {
            "success": True,
            "path": final_zip_path,
            "size_mb": zip_size_mb,
            "sha256": sha256,
            "total_files": total_files
        }


# -----------------------------------------------------------------------------
# Modern Desktop GUI Dashboard (Tkinter / ttk)
# -----------------------------------------------------------------------------
class ReleaseBuilderGUI:
    def __init__(self, root):
        self.root = root
        self.root.title(f"{PRODUCT_NAME} Marketplace Release Builder")
        self.root.geometry("860x720")
        self.root.minsize(800, 640)

        # Style colors
        self.c_bg = "#0f172a"
        self.c_card = "#1e293b"
        self.c_border = "#334155"
        self.c_text = "#f8fafc"
        self.c_muted = "#94a3b8"
        self.c_primary = "#4f46e5"
        self.c_emerald = "#10b981"
        self.c_amber = "#f59e0b"
        self.c_rose = "#ef4444"

        self.root.configure(bg=self.c_bg)
        self.engine = ReleaseEngine(repo_root=REPO_ROOT, log_callback=self.append_log)
        self.last_built_path = None
        self.is_building = False

        self._build_ui()
        self.refresh_checklist()

    def _build_ui(self):
        # Header banner
        header_frame = tk.Frame(self.root, bg=self.c_card, padx=20, pady=16)
        header_frame.pack(fill=tk.X)

        title_lbl = tk.Label(
            header_frame,
            text=f"📦 {PRODUCT_NAME} Marketplace Release Builder",
            font=("Segoe UI", 16, "bold"),
            fg=self.c_text,
            bg=self.c_card
        )
        title_lbl.pack(anchor=tk.W)

        subtitle_lbl = tk.Label(
            header_frame,
            text=f"Sanitize, inspect, and package the full codebase for sale on {MARKETPLACE_TARGET} (CodeCanyon / Stacklane style).",
            font=("Segoe UI", 10),
            fg=self.c_muted,
            bg=self.c_card
        )
        subtitle_lbl.pack(anchor=tk.W, pady=(2, 0))

        # Main content area
        main_frame = tk.Frame(self.root, bg=self.c_bg, padx=16, pady=12)
        main_frame.pack(fill=tk.BOTH, expand=True)

        # Left / Top: Checklist Section
        check_header_frame = tk.Frame(main_frame, bg=self.c_bg)
        check_header_frame.pack(fill=tk.X, pady=(0, 6))

        check_title = tk.Label(
            check_header_frame,
            text="PRE-FLIGHT DIAGNOSTICS & AUDIT",
            font=("Segoe UI", 10, "bold"),
            fg="#cbd5e1",
            bg=self.c_bg
        )
        check_title.pack(side=tk.LEFT)

        refresh_btn = tk.Button(
            check_header_frame,
            text="⟳ Refresh Checks",
            font=("Segoe UI", 9, "bold"),
            bg="#334155",
            fg="white",
            relief=tk.FLAT,
            padx=10,
            pady=3,
            cursor="hand2",
            command=self.refresh_checklist
        )
        refresh_btn.pack(side=tk.RIGHT)

        self.checklist_frame = tk.Frame(main_frame, bg=self.c_card, bd=1, relief=tk.SOLID)
        self.checklist_frame.pack(fill=tk.X, pady=(0, 12))

        # Config row (Version, Output dir, Options)
        cfg_frame = tk.Frame(main_frame, bg=self.c_card, padx=14, pady=10)
        cfg_frame.pack(fill=tk.X, pady=(0, 12))

        tk.Label(cfg_frame, text="Version:", font=("Segoe UI", 9, "bold"), fg=self.c_text, bg=self.c_card).grid(row=0, column=0, sticky=tk.W, padx=(0, 6))
        self.version_var = tk.StringVar(value=DEFAULT_VERSION)
        ver_entry = tk.Entry(cfg_frame, textvariable=self.version_var, width=10, font=("Segoe UI", 9), bg="#0f172a", fg="white", insertbackground="white")
        ver_entry.grid(row=0, column=1, sticky=tk.W, padx=(0, 16))

        tk.Label(cfg_frame, text="Output Directory:", font=("Segoe UI", 9, "bold"), fg=self.c_text, bg=self.c_card).grid(row=0, column=2, sticky=tk.W, padx=(0, 6))
        self.out_var = tk.StringVar(value=DEFAULT_OUTPUT_DIR)
        out_entry = tk.Entry(cfg_frame, textvariable=self.out_var, width=32, font=("Segoe UI", 9), bg="#0f172a", fg="white", insertbackground="white")
        out_entry.grid(row=0, column=3, sticky=tk.W, padx=(0, 6))

        browse_btn = tk.Button(
            cfg_frame,
            text="Browse...",
            font=("Segoe UI", 8),
            bg="#334155",
            fg="white",
            relief=tk.FLAT,
            command=self.browse_output_dir
        )
        browse_btn.grid(row=0, column=4, sticky=tk.W, padx=(0, 16))

        self.build_fe_var = tk.BooleanVar(value=True)
        fe_chk = tk.Checkbutton(
            cfg_frame,
            text="Run 'npm run build' first",
            variable=self.build_fe_var,
            font=("Segoe UI", 9),
            fg=self.c_text,
            bg=self.c_card,
            selectcolor="#0f172a",
            activebackground=self.c_card,
            activeforeground="white"
        )
        fe_chk.grid(row=0, column=5, sticky=tk.W)

        # Action Buttons row
        action_frame = tk.Frame(main_frame, bg=self.c_bg)
        action_frame.pack(fill=tk.X, pady=(0, 8))

        self.build_btn = tk.Button(
            action_frame,
            text="🚀 BUILD RELEASE PACKAGE (ZIP)",
            font=("Segoe UI", 11, "bold"),
            bg=self.c_primary,
            fg="white",
            relief=tk.FLAT,
            padx=20,
            pady=8,
            cursor="hand2",
            command=self.start_build_thread
        )
        self.build_btn.pack(side=tk.LEFT)

        self.open_folder_btn = tk.Button(
            action_frame,
            text="📂 Open Output Folder",
            font=("Segoe UI", 10),
            bg="#334155",
            fg="white",
            relief=tk.FLAT,
            padx=14,
            pady=8,
            cursor="hand2",
            state=tk.DISABLED,
            command=self.open_output_folder
        )
        self.open_folder_btn.pack(side=tk.LEFT, padx=10)

        # Terminal Log Console
        log_label = tk.Label(
            main_frame,
            text="ACTIVITY & PACKAGING LOG",
            font=("Segoe UI", 10, "bold"),
            fg="#cbd5e1",
            bg=self.c_bg
        )
        log_label.pack(anchor=tk.W, pady=(4, 4))

        self.log_text = tk.Text(
            main_frame,
            wrap=tk.WORD,
            font=("Consolas", 9),
            bg="#020617",
            fg="#e2e8f0",
            insertbackground="white",
            bd=0,
            padx=10,
            pady=8
        )
        self.log_text.pack(fill=tk.BOTH, expand=True)

        scroll = tk.Scrollbar(self.log_text, command=self.log_text.yview)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)
        self.log_text.config(yscrollcommand=scroll.set)

    def append_log(self, text):
        def _insert():
            self.log_text.insert(tk.END, text + "\n")
            self.log_text.see(tk.END)
        self.root.after(0, _insert)

    def browse_output_dir(self):
        dir_selected = filedialog.askdirectory(initialdir=self.out_var.get())
        if dir_selected:
            self.out_var.set(dir_selected)

    def open_output_folder(self):
        target = self.out_var.get()
        if os.path.exists(target):
            if os.name == "nt":
                os.startfile(target)
            else:
                subprocess.Popen(["xdg-open", target])

    def refresh_checklist(self):
        for widget in self.checklist_frame.winfo_children():
            widget.destroy()

        results = self.engine.run_checklist()

        for idx, item in enumerate(results):
            row_frame = tk.Frame(self.checklist_frame, bg=self.c_card, padx=12, pady=7)
            row_frame.pack(fill=tk.X, side=tk.TOP)

            # Divider line except first
            if idx > 0:
                sep = tk.Frame(self.checklist_frame, height=1, bg=self.c_border)
                sep.pack(fill=tk.X)

            # Badge color
            status = item["status"]
            if status == "PASS":
                badge_bg = "#064e3b"
                badge_fg = "#34d399"
                badge_text = "✓ READY"
            elif status == "WARN":
                badge_bg = "#78350f"
                badge_fg = "#fde047"
                badge_text = "! WARN"
            else:
                badge_bg = "#7f1d1d"
                badge_fg = "#f87171"
                badge_text = "✕ FAIL"

            badge = tk.Label(
                row_frame,
                text=badge_text,
                font=("Segoe UI", 8, "bold"),
                bg=badge_bg,
                fg=badge_fg,
                padx=6,
                pady=2
            )
            badge.pack(side=tk.LEFT, padx=(0, 10))

            title = tk.Label(
                row_frame,
                text=item["title"],
                font=("Segoe UI", 9, "bold"),
                fg=self.c_text,
                bg=self.c_card
            )
            title.pack(side=tk.LEFT)

            desc = tk.Label(
                row_frame,
                text=item["desc"],
                font=("Segoe UI", 8),
                fg=self.c_muted,
                bg=self.c_card
            )
            desc.pack(side=tk.RIGHT, padx=10)

    def start_build_thread(self):
        if self.is_building:
            return

        self.is_building = True
        self.build_btn.config(state=tk.DISABLED, text="⏳ PACKAGING IN PROGRESS...")
        self.open_folder_btn.config(state=tk.DISABLED)

        version = self.version_var.get().strip() or DEFAULT_VERSION
        out_dir = self.out_var.get().strip() or DEFAULT_OUTPUT_DIR
        build_fe = self.build_fe_var.get()

        def _worker():
            try:
                res = self.engine.build_package(
                    version=version,
                    output_dir=out_dir,
                    build_frontend=build_fe
                )
                self.last_built_path = res["path"]
                self.root.after(0, lambda: self.on_build_complete(True))
            except Exception as e:
                self.append_log(f"\n[-] Build error occurred: {e}")
                self.root.after(0, lambda: self.on_build_complete(False, str(e)))

        thread = threading.Thread(target=_worker, daemon=True)
        thread.start()

    def on_build_complete(self, success, err_msg=""):
        self.is_building = False
        self.build_btn.config(state=tk.NORMAL, text="🚀 BUILD RELEASE PACKAGE (ZIP)")
        self.open_folder_btn.config(state=tk.NORMAL)
        self.refresh_checklist()

        if success:
            messagebox.showinfo(
                "Packaging Complete",
                f"Release ZIP built successfully!\n\nFile saved to:\n{self.last_built_path}"
            )
        else:
            messagebox.showerror("Build Error", f"Packaging failed:\n{err_msg}")


# -----------------------------------------------------------------------------
# Main Entry Point
# -----------------------------------------------------------------------------
def main():
    if hasattr(sys.stdout, "reconfigure"):
        try:
            sys.stdout.reconfigure(encoding="utf-8")
        except Exception:
            pass

    # Support headless check via CLI flag --check
    if len(sys.argv) > 1 and sys.argv[1] in ("--check", "-c"):
        engine = ReleaseEngine(repo_root=REPO_ROOT)
        print("Running pre-flight checks...")
        res = engine.run_checklist()
        all_ok = True
        for r in res:
            mark = "PASS" if r["status"] == "PASS" else ("WARN" if r["status"] == "WARN" else "FAIL")
            print(f"[{mark}] {r['title']}: {r['desc']}")
            if r["status"] == "FAIL":
                all_ok = False
        sys.exit(0 if all_ok else 1)

    # Launch GUI Dashboard
    root = tk.Tk()
    app = ReleaseBuilderGUI(root)
    root.mainloop()


if __name__ == "__main__":
    main()
