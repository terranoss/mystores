import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { StoreProvider } from './context/StoreContext';
import ErrorBoundary from './components/ErrorBoundary';
import ProtectedRoute from './components/ProtectedRoute';
import AppShell from './components/AppShell';
import Login from './pages/Login';
import InstallWizard from './pages/InstallWizard';
import { ToastProvider } from './context/ToastContext';

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <StoreProvider>
          <ToastProvider>
            <ErrorBoundary>
              <Routes>
                <Route path="/login" element={<Login />} />
                <Route path="/install" element={<InstallWizard />} />
                <Route
                  path="/*"
                  element={
                    <ProtectedRoute>
                      <AppShell />
                    </ProtectedRoute>
                  }
                />
              </Routes>
            </ErrorBoundary>
          </ToastProvider>
        </StoreProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
