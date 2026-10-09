import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

interface PreflightCheck {
  name: string;
  current?: string;
  pass: boolean;
}

interface PreflightResponse {
  ready: boolean;
  checks: Record<string, PreflightCheck>;
}

export default function InstallWizard() {
  const navigate = useNavigate();
  const [step, setStep] = useState<number>(1);
  const [loading, setLoading] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Preflight state
  const [preflightData, setPreflightData] = useState<PreflightResponse | null>(null);
  const [checkingPreflight, setCheckingPreflight] = useState<boolean>(true);

  // Form states
  const [licenseKey, setLicenseKey] = useState<string>('');
  const [licenseVerified, setLicenseVerified] = useState<boolean>(false);

  // Database settings
  const [dbConnection, setDbConnection] = useState<string>('mysql');
  const [dbHost, setDbHost] = useState<string>('127.0.0.1');
  const [dbPort, setDbPort] = useState<string>('3306');
  const [dbDatabase, setDbDatabase] = useState<string>('mystores');
  const [dbUsername, setDbUsername] = useState<string>('root');
  const [dbPassword, setDbPassword] = useState<string>('');
  const [dbTested, setDbTested] = useState<boolean>(false);

  // App & Admin settings
  const [appName, setAppName] = useState<string>('MyStores');
  const [appUrl, setAppUrl] = useState<string>(window.location.origin);
  const [storeName, setStoreName] = useState<string>('Main Store');
  const [adminName, setAdminName] = useState<string>('Admin');
  const [adminEmail, setAdminEmail] = useState<string>('admin@example.com');
  const [adminPassword, setAdminPassword] = useState<string>('');
  const [showPassword, setShowPassword] = useState<boolean>(false);

  // Check if already installed
  useEffect(() => {
    axios
      .get('/api/install/status')
      .then((res) => {
        if (res.data?.installed) {
          navigate('/login');
        }
      })
      .catch(() => {});

    runPreflight();
  }, [navigate]);

  const runPreflight = async () => {
    setCheckingPreflight(true);
    setErrorMsg(null);
    try {
      const res = await axios.get<PreflightResponse>('/api/install/preflight');
      setPreflightData(res.data);
    } catch {
      setErrorMsg('Failed to run server preflight checks. Please verify your backend server is running.');
    } finally {
      setCheckingPreflight(false);
    }
  };

  const handleActivateLicense = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setSuccessMsg(null);
    if (!licenseKey.trim()) {
      setErrorMsg('Please enter your license key.');
      return;
    }

    setLoading(true);
    try {
      const res = await axios.post('/api/install/activate-license', {
        license_key: licenseKey.trim(),
        domain: window.location.hostname,
      });

      if (res.data?.success) {
        setLicenseVerified(true);
        setSuccessMsg(res.data.message || 'License successfully verified with Stacklane!');
        setTimeout(() => {
          setSuccessMsg(null);
          setStep(3);
        }, 1200);
      } else {
        setErrorMsg(res.data?.message || 'Activation failed.');
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      setErrorMsg(error.response?.data?.message || 'Unable to verify license key with Stacklane.');
    } finally {
      setLoading(false);
    }
  };

  const handleTestDatabase = async () => {
    setErrorMsg(null);
    setSuccessMsg(null);
    setLoading(true);
    try {
      const res = await axios.post('/api/install/test-db', {
        db_connection: dbConnection,
        db_host: dbHost,
        db_port: dbPort,
        db_database: dbDatabase,
        db_username: dbUsername,
        db_password: dbPassword,
      });

      if (res.data?.success) {
        setDbTested(true);
        setSuccessMsg(res.data.message || 'Database connection successful!');
      } else {
        setDbTested(false);
        setErrorMsg(res.data?.message || 'Database connection failed.');
      }
    } catch (err: unknown) {
      setDbTested(false);
      const error = err as { response?: { data?: { message?: string } } };
      setErrorMsg(error.response?.data?.message || 'Database test failed. Please verify credentials.');
    } finally {
      setLoading(false);
    }
  };

  const handleExecuteInstall = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    setSuccessMsg(null);

    if (adminPassword.length < 8) {
      setErrorMsg('Admin password must be at least 8 characters long.');
      return;
    }

    setLoading(true);
    try {
      const res = await axios.post('/api/install/execute', {
        license_key: licenseKey.trim(),
        db_connection: dbConnection,
        db_host: dbHost,
        db_port: dbPort,
        db_database: dbDatabase,
        db_username: dbUsername,
        db_password: dbPassword,
        app_name: appName,
        app_url: appUrl,
        admin_name: adminName,
        admin_email: adminEmail,
        admin_password: adminPassword,
        store_name: storeName,
      });

      if (res.data?.success) {
        setStep(5);
      } else {
        setErrorMsg(res.data?.message || 'Installation encountered an error.');
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      setErrorMsg(error.response?.data?.message || 'Installation execution failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between p-4 sm:p-8 font-sans selection:bg-indigo-500 selection:text-white">
      {/* Top Header */}
      <div className="max-w-4xl w-full mx-auto">
        <div className="flex items-center justify-between border-b border-slate-800 pb-5 mb-8">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20">
              MS
            </div>
            <div>
              <h1 className="text-xl font-bold tracking-tight text-white">MyStore Setup Wizard</h1>
              <p className="text-xs text-slate-400">Installation & Environment Setup</p>
            </div>
          </div>
          <div className="hidden sm:flex items-center gap-2">
            <span className="text-xs px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-medium">
              v1.0.0
            </span>
            <span className="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
              Self-Hosted
            </span>
          </div>
        </div>

        {/* Stepper Progress */}
        <div className="grid grid-cols-5 gap-2 mb-8">
          {[
            { num: 1, title: 'Preflight' },
            { num: 2, title: 'License' },
            { num: 3, title: 'Database' },
            { num: 4, title: 'Admin' },
            { num: 5, title: 'Ready' },
          ].map((s) => (
            <div
              key={s.num}
              className={`flex flex-col items-center p-2.5 rounded-lg border text-center transition-all ${
                step === s.num
                  ? 'border-indigo-500 bg-indigo-500/10 text-white font-semibold shadow-sm'
                  : step > s.num
                  ? 'border-emerald-500/30 bg-emerald-500/5 text-emerald-400'
                  : 'border-slate-800 bg-slate-900/50 text-slate-500'
              }`}
            >
              <span className="text-xs tracking-wider uppercase font-mono">Step {s.num}</span>
              <span className="text-sm truncate w-full">{s.title}</span>
            </div>
          ))}
        </div>

        {/* Feedback Alerts */}
        {errorMsg && (
          <div className="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start gap-3 animate-fadeIn">
            <span className="text-rose-400 font-bold mt-0.5">✕</span>
            <div>{errorMsg}</div>
          </div>
        )}
        {successMsg && (
          <div className="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-start gap-3 animate-fadeIn">
            <span className="text-emerald-400 font-bold mt-0.5">✓</span>
            <div>{successMsg}</div>
          </div>
        )}

        {/* Content Container */}
        <div className="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 sm:p-8 backdrop-blur-sm shadow-xl">
          {/* STEP 1: PREFLIGHT */}
          {step === 1 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-semibold text-white">System Preflight Requirements</h2>
                <p className="text-sm text-slate-400 mt-1">
                  Checking PHP extensions and file write permissions required for MyStore.
                </p>
              </div>

              {checkingPreflight ? (
                <div className="py-12 flex flex-col items-center justify-center gap-3">
                  <div className="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
                  <p className="text-sm text-slate-400">Verifying server environment...</p>
                </div>
              ) : preflightData ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {Object.entries(preflightData.checks).map(([key, item]) => (
                      <div
                        key={key}
                        className={`flex items-center justify-between p-3.5 rounded-xl border text-sm ${
                          item.pass
                            ? 'bg-emerald-500/5 border-emerald-500/20 text-slate-200'
                            : 'bg-rose-500/10 border-rose-500/30 text-rose-200'
                        }`}
                      >
                        <span className="font-medium text-xs sm:text-sm">{item.name}</span>
                        <span
                          className={`text-xs px-2.5 py-1 rounded-md font-semibold ${
                            item.pass
                              ? 'bg-emerald-500/20 text-emerald-300'
                              : 'bg-rose-500/20 text-rose-300'
                          }`}
                        >
                          {item.pass ? 'PASS' : 'FAIL'}
                        </span>
                      </div>
                    ))}
                  </div>

                  {!preflightData.ready && (
                    <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs">
                      One or more server requirements failed. Please enable the missing PHP extensions in your
                      hosting control panel (e.g. cPanel MultiPHP / PHP Extensions) and re-check.
                    </div>
                  )}

                  <div className="pt-4 flex justify-between items-center border-t border-slate-800">
                    <button
                      type="button"
                      onClick={runPreflight}
                      className="px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                    >
                      Re-check System
                    </button>
                    <button
                      type="button"
                      disabled={!preflightData.ready}
                      onClick={() => setStep(2)}
                      className="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold shadow-lg shadow-indigo-600/25 transition"
                    >
                      Continue to License &rarr;
                    </button>
                  </div>
                </div>
              ) : null}
            </div>
          )}

          {/* STEP 2: LICENSE ACTIVATION */}
          {step === 2 && (
            <form onSubmit={handleActivateLicense} className="space-y-6">
              <div>
                <h2 className="text-lg font-semibold text-white">License Key Activation</h2>
                <p className="text-sm text-slate-400 mt-1">
                  Enter your MyStore license key purchased from Stacklane to activate your installation.
                </p>
              </div>

              <div className="space-y-3">
                <label className="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-300">
                  <span>License Key</span>
                  {licenseVerified && <span className="text-emerald-400 font-medium normal-case">✓ Verified</span>}
                </label>
                <input
                  type="text"
                  required
                  placeholder="MSTR-XXXX-XXXX-XXXX"
                  value={licenseKey}
                  onChange={(e) => setLicenseKey(e.target.value.toUpperCase())}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-base text-white placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                />
                <p className="text-xs text-slate-500">
                  This key will be cryptographically bound to <span className="text-slate-300 font-mono">{window.location.hostname}</span>.
                </p>
              </div>

              <div className="p-4 rounded-xl bg-slate-950/60 border border-slate-800 text-xs text-slate-400 space-y-1">
                <p className="font-semibold text-slate-300">Privacy Notice:</p>
                <p>
                  Activation occurs once over HTTPS with Stacklane. No background heartbeats or recurring tracking calls will be made during normal daily usage.
                </p>
              </div>

              <div className="pt-4 flex justify-between items-center border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                >
                  &larr; Back
                </button>
                <button
                  type="submit"
                  disabled={loading || !licenseKey.trim()}
                  className="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-semibold shadow-lg shadow-indigo-600/25 transition flex items-center gap-2"
                >
                  {loading && <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />}
                  Verify & Activate
                </button>
              </div>
            </form>
          )}

          {/* STEP 3: DATABASE CONFIGURATION */}
          {step === 3 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-semibold text-white">Database Configuration</h2>
                <p className="text-sm text-slate-400 mt-1">
                  Connect MyStore to your MySQL or SQLite database.
                </p>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => {
                    setDbConnection('mysql');
                    setDbTested(false);
                  }}
                  className={`p-3.5 rounded-xl border text-left text-sm transition ${
                    dbConnection === 'mysql'
                      ? 'border-indigo-500 bg-indigo-500/10 text-white'
                      : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700'
                  }`}
                >
                  <div className="font-semibold text-white">MySQL / MariaDB</div>
                  <div className="text-xs text-slate-500 mt-0.5">Recommended for cPanel & VPS</div>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setDbConnection('sqlite');
                    setDbTested(false);
                  }}
                  className={`p-3.5 rounded-xl border text-left text-sm transition ${
                    dbConnection === 'sqlite'
                      ? 'border-indigo-500 bg-indigo-500/10 text-white'
                      : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700'
                  }`}
                >
                  <div className="font-semibold text-white">SQLite</div>
                  <div className="text-xs text-slate-500 mt-0.5">Single-file database</div>
                </button>
              </div>

              {dbConnection === 'mysql' ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Host</label>
                    <input
                      type="text"
                      value={dbHost}
                      onChange={(e) => {
                        setDbHost(e.target.value);
                        setDbTested(false);
                      }}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Port</label>
                    <input
                      type="text"
                      value={dbPort}
                      onChange={(e) => {
                        setDbPort(e.target.value);
                        setDbTested(false);
                      }}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Database Name</label>
                    <input
                      type="text"
                      value={dbDatabase}
                      onChange={(e) => {
                        setDbDatabase(e.target.value);
                        setDbTested(false);
                      }}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Username</label>
                    <input
                      type="text"
                      value={dbUsername}
                      onChange={(e) => {
                        setDbUsername(e.target.value);
                        setDbTested(false);
                      }}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">Password</label>
                    <input
                      type="password"
                      value={dbPassword}
                      onChange={(e) => {
                        setDbPassword(e.target.value);
                        setDbTested(false);
                      }}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </div>
              ) : (
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">SQLite File Path</label>
                  <input
                    type="text"
                    value={dbDatabase}
                    onChange={(e) => {
                      setDbDatabase(e.target.value);
                      setDbTested(false);
                    }}
                    placeholder="database/database.sqlite"
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              )}

              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={handleTestDatabase}
                  disabled={loading}
                  className="px-4 py-2 rounded-xl border border-indigo-500/30 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 text-xs font-semibold transition flex items-center gap-2"
                >
                  {loading && <div className="w-3.5 h-3.5 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" />}
                  Test Connection
                </button>
                {dbTested && (
                  <span className="text-xs text-emerald-400 flex items-center gap-1.5 font-medium">
                    <span>✓</span> Verified database connection
                  </span>
                )}
              </div>

              <div className="pt-4 flex justify-between items-center border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                >
                  &larr; Back
                </button>
                <button
                  type="button"
                  disabled={!dbTested}
                  onClick={() => setStep(4)}
                  className="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold shadow-lg shadow-indigo-600/25 transition"
                >
                  Continue &rarr;
                </button>
              </div>
            </div>
          )}

          {/* STEP 4: ADMIN & STORE SETUP */}
          {step === 4 && (
            <form onSubmit={handleExecuteInstall} className="space-y-6">
              <div>
                <h2 className="text-lg font-semibold text-white">Administrator & Store Setup</h2>
                <p className="text-sm text-slate-400 mt-1">
                  Create your primary superadmin login credentials and initial workspace.
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">Application Name</label>
                  <input
                    type="text"
                    required
                    value={appName}
                    onChange={(e) => setAppName(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">Store / Workspace Name</label>
                  <input
                    type="text"
                    required
                    value={storeName}
                    onChange={(e) => setStoreName(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">Application URL</label>
                  <input
                    type="url"
                    required
                    value={appUrl}
                    onChange={(e) => setAppUrl(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">Admin Full Name</label>
                  <input
                    type="text"
                    required
                    value={adminName}
                    onChange={(e) => setAdminName(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1.5">Admin Email</label>
                  <input
                    type="email"
                    required
                    value={adminEmail}
                    onChange={(e) => setAdminEmail(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
                <div className="sm:col-span-2">
                  <div className="flex justify-between items-center mb-1.5">
                    <label className="text-xs font-semibold text-slate-300">Admin Password (min. 8 characters)</label>
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="text-xs text-indigo-400 hover:text-indigo-300"
                    >
                      {showPassword ? 'Hide' : 'Show'}
                    </button>
                  </div>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    required
                    minLength={8}
                    value={adminPassword}
                    onChange={(e) => setAdminPassword(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              <div className="pt-4 flex justify-between items-center border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setStep(3)}
                  className="px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                >
                  &larr; Back
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-semibold shadow-lg shadow-indigo-600/25 transition flex items-center gap-2"
                >
                  {loading && <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />}
                  Complete Installation
                </button>
              </div>
            </form>
          )}

          {/* STEP 5: SUCCESS */}
          {step === 5 && (
            <div className="py-6 text-center space-y-6 animate-fadeIn">
              <div className="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center text-3xl mx-auto shadow-xl shadow-emerald-500/10">
                ✓
              </div>
              <div>
                <h2 className="text-2xl font-bold text-white">Installation Complete!</h2>
                <p className="text-sm text-slate-400 max-w-md mx-auto mt-2">
                  MyStore has been successfully initialized, database tables migrated, and superadmin credentials created.
                </p>
              </div>

              <div className="p-4 rounded-xl bg-slate-950 border border-slate-800 text-left max-w-md mx-auto space-y-2 text-xs">
                <div className="flex justify-between py-1 border-b border-slate-800">
                  <span className="text-slate-500">Store Name:</span>
                  <span className="font-semibold text-slate-200">{storeName}</span>
                </div>
                <div className="flex justify-between py-1 border-b border-slate-800">
                  <span className="text-slate-500">Admin Email:</span>
                  <span className="font-semibold text-slate-200">{adminEmail}</span>
                </div>
                <div className="flex justify-between py-1">
                  <span className="text-slate-500">Security Lock:</span>
                  <span className="text-emerald-400 font-medium">storage/installed created</span>
                </div>
              </div>

              <div>
                <button
                  type="button"
                  onClick={() => navigate('/login')}
                  className="px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-600/25 transition"
                >
                  Launch Dashboard & Login &rarr;
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Footer */}
      <div className="text-center text-xs text-slate-500 mt-8">
        &copy; {new Date().getFullYear()} MyStore. All rights reserved. Powered by Stacklane Licensing.
      </div>
    </div>
  );
}
