/**
 * Lets other parts of the owner/tenant shell (the Help and support page) open
 * the floating Help & feedback widget. The widget itself still owns the form
 * and the submit — this only asks it to open.
 */
export const useSupportWidget = () => {
  const openRequests = useState("support-widget-open-requests", () => 0);
  return {
    openRequests,
    open: () => {
      openRequests.value++;
    },
  };
};
