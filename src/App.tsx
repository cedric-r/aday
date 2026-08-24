import { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import { AuthProvider } from '@/context/AuthContext';
import { Header } from '@/components/Header';
import { ProtectedRoute } from '@/components/ProtectedRoute';

// Lazy-load pages to keep initial bundle small
const HomePage = lazy(() => import('@/pages/HomePage').then((m) => ({ default: m.HomePage })));
const RegisterPage = lazy(() => import('@/pages/RegisterPage').then((m) => ({ default: m.RegisterPage })));
const LoginPage = lazy(() => import('@/pages/LoginPage').then((m) => ({ default: m.LoginPage })));
const IndexPage = lazy(() => import('@/pages/IndexPage').then((m) => ({ default: m.IndexPage })));
const PhotographerPage = lazy(() => import('@/pages/PhotographerPage').then((m) => ({ default: m.PhotographerPage })));
const PostPage = lazy(() => import('@/pages/PostPage').then((m) => ({ default: m.PostPage })));
const AdminPage = lazy(() => import('@/pages/AdminPage').then((m) => ({ default: m.AdminPage })));
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage').then((m) => ({ default: m.NotFoundPage })));

const PageSpinner = () => (
  <Box display="flex" justifyContent="center" mt={8}>
    <CircularProgress />
  </Box>
);

export const App = () => (
  <BrowserRouter>
    <AuthProvider>
      <Header />
      <Box component="main" sx={{ pt: 2, px: { xs: 2, md: 4 } }}>
        <Suspense fallback={<PageSpinner />}>
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/index" element={<IndexPage />} />
            <Route path="/photographers/:username" element={<PhotographerPage />} />
            <Route
              path="/post"
              element={
                <ProtectedRoute>
                  <PostPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin"
              element={
                <ProtectedRoute requireAdmin>
                  <AdminPage />
                </ProtectedRoute>
              }
            />
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
      </Box>
    </AuthProvider>
  </BrowserRouter>
);
