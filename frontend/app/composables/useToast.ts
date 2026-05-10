import { ref } from "vue";

export type ToastTone = "default" | "success" | "danger";

export interface Toast {
  id: number;
  message: string;
  tone: ToastTone;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

export const useToast = () => {
  const show = (message: string, tone: ToastTone = "default") => {
    const id = ++nextId;
    toasts.value.push({ id, message, tone });
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 3000);
  };

  return { toasts, show };
};
