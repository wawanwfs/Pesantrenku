import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import '../css/app.css';

const pages = {
  App: () => import('./Pages/App.jsx'),
  Login: () => import('./Pages/Login.jsx'),
};

createInertiaApp({
  resolve: async (name) => (await pages[name]()).default,
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
