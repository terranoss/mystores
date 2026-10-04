import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setLoading(true);
    try {
      await login(email, password);
      navigate('/dashboard');
    } catch (err: any) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors);
      } else if (err.response?.status === 401) {
        setErrors({ email: [err.response.data.message] });
      } else {
        setErrors({ email: ['Something went wrong.'] });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-ink-50 flex items-center justify-center p-4">
      <div className="w-full max-w-sm">
        {/* Logo */}
        <div className="text-center mb-10">
          <h1 className="font-heading text-3xl font-bold text-ink-950 tracking-tight">
            My<span className="text-clay-600">Stores</span>
          </h1>
          <p className="text-ink-400 text-sm mt-1.5 font-medium">Sign in to manage your stores</p>
        </div>

        <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-sm border border-ink-100 p-8 space-y-5">
          <div>
            <label className="block text-sm font-medium text-ink-700 mb-1.5">Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full px-4 py-2.5 bg-ink-50 border border-ink-200 rounded-xl text-ink-900 placeholder-ink-300 focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400"
              placeholder="you@example.com"
              required
            />
            {errors.email && <p className="mt-1.5 text-sm text-red-500 font-medium">{errors.email[0]}</p>}
          </div>

          <div>
            <label className="block text-sm font-medium text-ink-700 mb-1.5">Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-4 py-2.5 bg-ink-50 border border-ink-200 rounded-xl text-ink-900 placeholder-ink-300 focus:outline-none focus:ring-2 focus:ring-clay-400/40 focus:border-clay-400 transition"
              placeholder="••••••••"
              required
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-2.5 px-4 bg-ink-950 text-white font-semibold rounded-xl hover:bg-ink-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all active:scale-[0.98]"
          >
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>
      </div>
    </div>
  );
}
