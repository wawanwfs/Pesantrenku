// Bus toast sederhana: komponen mana pun dapat memanggil toast.success(...) tanpa prop drilling.
const listeners = new Set();
let counter = 0;

function emit(tone, message, options = {}) {
  counter += 1;
  const toast = { id: counter, tone, message, title: options.title, duration: options.duration ?? 5200 };
  listeners.forEach((listener) => listener(toast));
  return toast.id;
}

export const toast = {
  success: (message, options) => emit('success', message, options),
  info: (message, options) => emit('info', message, options),
  warning: (message, options) => emit('warning', message, options),
  error: (message, options) => emit('error', message, { duration: 7000, ...options }),
  subscribe(listener) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};
