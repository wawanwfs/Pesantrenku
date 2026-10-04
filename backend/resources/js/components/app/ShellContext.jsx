import { createContext, useContext } from 'react';

export const ShellContext = createContext({ panel: 'super-admin', openPalette: () => {}, openGuide: () => {}, auth: null, app: null });

export function useShell() {
  return useContext(ShellContext);
}
