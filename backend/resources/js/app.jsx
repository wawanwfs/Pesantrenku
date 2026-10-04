import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import '../css/app.css';
import { installPrintTheme } from './lib/theme.js';

installPrintTheme();

const pages = {
  App: () => import('./Pages/App.jsx'),
  Landing: () => import('./Pages/Landing.jsx'),
  Login: () => import('./Pages/Login.jsx'),
};

createInertiaApp({
  resolve: async (name) => (await pages[name]()).default,
  progress: false,
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
