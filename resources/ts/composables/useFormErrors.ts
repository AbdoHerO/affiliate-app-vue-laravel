import { reactive } from 'vue';
import type { FieldErrors } from '@/services/ErrorService';

export function useFormErrors<T extends Record<string, any>>() {
  const errors = reactive<Partial<Record<keyof T & string, string[]>>>({});

  const set = (fe?: FieldErrors) => {
    clear();
    if (!fe) return;
    for (const k of Object.keys(fe)) (errors as any)[k] = fe[k];
  };

  const clear = () => {
    for (const k of Object.keys(errors)) delete (errors as any)[k];
  };

  const asText = (fallback = '') =>
    Object.values(errors).flat().join('\n') || fallback;

  return { errors, set, clear, asText };
}
