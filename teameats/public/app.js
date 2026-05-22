const { createApp, ref, computed, onMounted } = Vue;

function formatDate(dateStr) {
  const [y, m, d] = dateStr.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('de-DE', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  });
}

function groupByDate(list, ascending = true) {
  const groups = {};
  for (const idea of list) {
    (groups[idea.date] ??= []).push(idea);
  }
  return Object.entries(groups)
    .sort(([a], [b]) => ascending ? a.localeCompare(b) : b.localeCompare(a))
    .map(([date, items]) => ({ date, items }));
}

const todayStr = (() => {
  const d = new Date();
  return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
})();

createApp({
  setup() {
    // Core data
    const ideas      = ref([]);
    const predefined = ref([]);
    const loading    = ref(true);
    const submitting = ref(false);

    // Add idea modal
    const showAddModal = ref(false);
    const formType     = ref('predefined');
    const form         = ref({ date: '', predefinedId: '', idea: '', description: '', menu_url: '', proposed_by: '', email: '' });
    const formError    = ref('');

    // Detail modal
    const selectedIdea      = ref(null);
    const registrations     = ref([]);
    const loadingRegs       = ref(false);
    const showDeleteConfirm = ref(false);
    const deleteEmail       = ref('');
    const deleteError       = ref('');

    // Register form
    const regForm  = ref({ name: '', email: '', comment: '' });
    const regError = ref('');

    // Unregister
    const unregisterTarget = ref(null);
    const unregisterEmail  = ref('');
    const unregisterError  = ref('');

    // ── Computed ──────────────────────────────────────────────────────────────

    const upcomingIdeas = computed(() =>
      groupByDate(ideas.value.filter(i => i.date >= todayStr))
    );

    const pastIdeas = computed(() =>
      groupByDate(ideas.value.filter(i => i.date < todayStr), false)
    );

    const showPastIdeas = ref(false);

    const selectedPredefined = computed(() =>
      predefined.value.find(p => p.id === form.value.predefinedId) ?? null
    );

    const selectedIdeaIsPast = computed(() =>
      selectedIdea.value ? selectedIdea.value.date < todayStr : false
    );

    // ── Data loading ──────────────────────────────────────────────────────────

    async function loadIdeas() {
      loading.value = true;
      try {
        ideas.value = await fetch('/api/ideas').then(r => r.json());
      } finally {
        loading.value = false;
      }
    }

    async function loadPredefined() {
      predefined.value = await fetch('/api/predefined').then(r => r.json());
    }

    // ── Add idea modal ────────────────────────────────────────────────────────

    function openAddModal() {
      form.value      = { date: '', predefinedId: '', idea: '', description: '', menu_url: '', proposed_by: '', email: '' };
      formType.value  = 'predefined';
      formError.value = '';
      showAddModal.value = true;
    }

    function closeAddModal() {
      showAddModal.value = false;
    }

    function onPredefinedChange() {
      form.value.description = selectedPredefined.value?.description ?? '';
      form.value.menu_url    = selectedPredefined.value?.menu_url    ?? '';
    }

    async function submitIdea() {
      formError.value = '';

      const ideaName = formType.value === 'predefined'
        ? (selectedPredefined.value?.name ?? '')
        : form.value.idea.trim();

      if (!form.value.date)               return (formError.value = 'Bitte wähle ein Datum aus.');
      if (!ideaName)                      return (formError.value = 'Bitte wähle oder gib eine Mittagsidee ein.');
      if (!form.value.proposed_by.trim()) return (formError.value = 'Bitte gib deinen Namen ein.');
      if (!form.value.email.trim())       return (formError.value = 'Bitte gib deine E-Mail-Adresse ein.');

      submitting.value = true;
      try {
        const res = await fetch('/api/ideas', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            date:        form.value.date,
            idea:        ideaName,
            description: form.value.description.trim() || null,
            image_url:   formType.value === 'predefined' ? (selectedPredefined.value?.image_url ?? null) : null,
            menu_url:    form.value.menu_url.trim() || null,
            proposed_by: form.value.proposed_by.trim(),
            email:       form.value.email.trim(),
          }),
        });

        if (!res.ok) {
          formError.value = (await res.json()).error ?? 'Der Vorschlag konnte nicht gespeichert werden.';
          return;
        }

        ideas.value.push(await res.json());
        closeAddModal();
      } finally {
        submitting.value = false;
      }
    }

    // ── Idea detail modal ─────────────────────────────────────────────────────

    async function openIdea(idea) {
      selectedIdea.value      = idea;
      registrations.value     = [];
      regForm.value           = { name: '', email: '', comment: '' };
      regError.value          = '';
      unregisterTarget.value  = null;
      showDeleteConfirm.value = false;
      deleteEmail.value       = '';
      deleteError.value       = '';
      await loadRegistrations(idea.id);
    }

    function closeIdea() {
      selectedIdea.value = null;
    }

    async function loadRegistrations(ideaId) {
      loadingRegs.value = true;
      try {
        registrations.value = await fetch(`/api/ideas/${ideaId}/registrations`).then(r => r.json());
      } finally {
        loadingRegs.value = false;
      }
    }

    // ── Register ──────────────────────────────────────────────────────────────

    async function submitRegistration() {
      regError.value = '';
      if (!regForm.value.name.trim())  return (regError.value = 'Bitte gib deinen Namen ein.');
      if (!regForm.value.email.trim()) return (regError.value = 'Bitte gib deine E-Mail-Adresse ein.');

      submitting.value = true;
      try {
        const res = await fetch(`/api/ideas/${selectedIdea.value.id}/registrations`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name:    regForm.value.name.trim(),
            email:   regForm.value.email.trim(),
            comment: regForm.value.comment.trim() || null,
          }),
        });

        if (!res.ok) {
          regError.value = (await res.json()).error ?? 'Anmeldung fehlgeschlagen.';
          return;
        }

        registrations.value.push(await res.json());
        const idea = ideas.value.find(i => i.id === selectedIdea.value.id);
        if (idea) idea.registration_count++;
        regForm.value = { name: '', email: '', comment: '' };
      } finally {
        submitting.value = false;
      }
    }

    // ── Unregister ────────────────────────────────────────────────────────────

    function startUnregister(reg) {
      unregisterTarget.value = reg;
      unregisterEmail.value  = '';
      unregisterError.value  = '';
    }

    async function submitUnregister() {
      unregisterError.value = '';
      if (!unregisterEmail.value.trim()) return (unregisterError.value = 'Bitte gib deine E-Mail-Adresse ein.');

      submitting.value = true;
      try {
        const res = await fetch(`/api/registrations/${unregisterTarget.value.id}`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: unregisterEmail.value.trim() }),
        });

        if (!res.ok) {
          unregisterError.value = (await res.json()).error ?? 'Abmeldung fehlgeschlagen.';
          return;
        }

        registrations.value = registrations.value.filter(r => r.id !== unregisterTarget.value.id);
        const idea = ideas.value.find(i => i.id === selectedIdea.value.id);
        if (idea) idea.registration_count--;
        unregisterTarget.value = null;
      } finally {
        submitting.value = false;
      }
    }

    // ── Delete idea ───────────────────────────────────────────────────────────

    async function submitDeleteIdea() {
      deleteError.value = '';
      submitting.value  = true;
      try {
        const res = await fetch(`/api/ideas/${selectedIdea.value.id}`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: deleteEmail.value.trim() }),
        });

        if (!res.ok) {
          deleteError.value = (await res.json()).error ?? 'Löschen fehlgeschlagen.';
          return;
        }

        ideas.value = ideas.value.filter(i => i.id !== selectedIdea.value.id);
        closeIdea();
      } finally {
        submitting.value = false;
      }
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    onMounted(() => {
      loadIdeas();
      loadPredefined();
    });

    return {
      ideas, predefined, loading, submitting,
      showAddModal, formType, form, formError, selectedPredefined,
      selectedIdea, registrations, loadingRegs, selectedIdeaIsPast,
      showDeleteConfirm, deleteEmail, deleteError,
      regForm, regError,
      unregisterTarget, unregisterEmail, unregisterError,
      upcomingIdeas, pastIdeas, showPastIdeas,
      todayStr,
      formatDate,
      openAddModal, closeAddModal, onPredefinedChange, submitIdea,
      openIdea, closeIdea,
      submitRegistration,
      startUnregister, submitUnregister,
      submitDeleteIdea,
    };
  },
}).mount('#app');
