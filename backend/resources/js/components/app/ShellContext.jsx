import { createContext, useContext } from 'react';

export const ShellContext = createContext({ panel: 'super-admin', openPalette: () => {}, auth: null });

export function useShell() {
  return useContext(ShellContext);
}
