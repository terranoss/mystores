import { AppShellLayoutProvider } from './PageShell';
import Layout from './Layout';
import KeepAlivePages from './KeepAlivePages';
import { useAuth } from '../context/AuthContext';

/** Single Layout + selective keep-alive for authenticated app pages. */
export default function AppShell() {
  const { user } = useAuth();
  return (
    <AppShellLayoutProvider>
      <Layout>
        <KeepAlivePages key={user?.id ?? 'anon'} />
      </Layout>
    </AppShellLayoutProvider>
  );
}
