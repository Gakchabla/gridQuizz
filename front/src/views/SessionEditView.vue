<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { apiFetch } from '../api/client'
import ColorDot from '../components/ColorDot.vue'

const props = defineProps({ id: [String, Number] })

const session = ref(null)
const themes = ref([])
const questions = ref([])
const players = ref([])
const loading = ref(true)
const error = ref(null)
const dirtyQuestions = ref(false)
const savingQuestions = ref(false)
const addingRound = ref(false)

const gameInProgress = computed(() => questions.value.some((q) => q.number !== null))

const normalThemes = computed(() => themes.value.filter((t) => !t.bonus))
const bonusThemes = computed(() => themes.value.filter((t) => t.bonus))
// Bonus theme always shown last, wherever themes are listed.
const orderedThemes = computed(() => [...normalThemes.value, ...bonusThemes.value])

function questionsFor(theme) {
  return questions.value.filter((q) => q.theme === theme['@id'])
}

function questionCountFor(theme) {
  return questionsFor(theme).length
}

function playerFor(theme) {
  return players.value.find((p) => p.theme === theme['@id'])
}

// See PLAN.md: 1 player per normal theme, all normal themes have the same
// question count Q, and the bonus theme has one question per theme/player
// combo (= number of normal themes) — so everyone answers Q+1 questions by
// the end, however they pick.
const readiness = computed(() => {
  const issues = []

  if (bonusThemes.value.length !== 1) {
    issues.push('Il faut exactement une catégorie bonus.')
  }
  if (!normalThemes.value.length) {
    issues.push('Ajoutez au moins un thème normal.')
  }
  if (normalThemes.value.some((t) => !playerFor(t))) {
    issues.push('Chaque thème normal doit avoir un joueur.')
  }

  const normalCounts = normalThemes.value.map(questionCountFor)
  const questionsPerNormalTheme = normalCounts[0] ?? 0
  if (normalCounts.length && (questionsPerNormalTheme < 1 || normalCounts.some((c) => c !== questionsPerNormalTheme))) {
    issues.push('Tous les thèmes normaux doivent avoir le même nombre de questions (au moins 1).')
  }
  if (bonusThemes.value.length === 1 && questionCountFor(bonusThemes.value[0]) !== normalThemes.value.length) {
    issues.push(`La catégorie bonus doit avoir exactement ${normalThemes.value.length} question(s).`)
  }

  return { ready: issues.length === 0, issues, questionsPerPlayer: questionsPerNormalTheme + 1 }
})

// 10 hues spaced for mutual distinctness (validated with the dataviz skill's
// palette checker against this app's dark surface: lightness band, chroma
// floor and contrast all pass; the two closest pairs still rely on the theme
// name always being shown next to the swatch).
const THEME_COLORS = [
  '#da1a69',
  '#e84415',
  '#da7e1a',
  '#e2b936',
  '#569f18',
  '#03816b',
  '#318eb5',
  '#8050eb',
  '#b31ce4',
  '#e51eb9',
]

const usedColors = computed(() => new Set(themes.value.map((t) => t.color)))

const DEFAULT_NORMAL_QUESTION_COUNT = 5

const newTheme = ref({ name: '', color: '', bonus: false, playerName: '' })

async function load() {
  loading.value = true
  try {
    // Single request for session + themes + questions + players instead of
    // 4 separate ones — each request pays a fixed latency floor through
    // Docker Desktop's Windows host port-forwarding, so cutting round-trips
    // is what actually moves the needle.
    const state = await apiFetch(`/api/sessions/${props.id}/state`)
    session.value = state.session
    themes.value = state.themes
    questions.value = state.questions
    players.value = state.players
    await ensureBonusTheme()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// Every session should have exactly one bonus category; create it
// automatically so nobody has to remember the checkbox for it.
async function ensureBonusTheme() {
  if (bonusThemes.value.length > 0) {
    return
  }
  const theme = await apiFetch('/api/themes', {
    method: 'POST',
    body: JSON.stringify({ session: `/api/sessions/${props.id}`, name: 'Bonus', color: 'rainbow', bonus: true }),
  })
  themes.value.push(theme)
  await addBlankQuestions(theme, normalThemes.value.length)
}

async function saveParams() {
  await apiFetch(`/api/sessions/${props.id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/merge-patch+json' },
    body: JSON.stringify({
      name: session.value.name,
      revealDuration: session.value.revealDuration,
      shuffled: session.value.shuffled,
    }),
  })
}

async function addBlankQuestions(theme, count) {
  if (count <= 0) {
    return
  }
  const created = await Promise.all(
    Array.from({ length: count }, () =>
      apiFetch('/api/questions', {
        method: 'POST',
        body: JSON.stringify({
          session: `/api/sessions/${props.id}`,
          theme: theme['@id'],
          questionText: '',
          answerText: '',
        }),
      }),
    ),
  )
  questions.value.push(...created)
}

async function addTheme() {
  const isBonus = newTheme.value.bonus
  // Capture the target count before the new theme exists, so it doesn't skew its own average.
  const target = isBonus
    ? normalThemes.value.length
    : normalThemes.value.length
      ? questionCountFor(normalThemes.value[0])
      : DEFAULT_NORMAL_QUESTION_COUNT

  const theme = await apiFetch('/api/themes', {
    method: 'POST',
    body: JSON.stringify({
      session: `/api/sessions/${props.id}`,
      name: newTheme.value.name,
      color: newTheme.value.color,
      bonus: isBonus,
    }),
  })
  themes.value.push(theme)

  if (!isBonus) {
    const player = await apiFetch('/api/players', {
      method: 'POST',
      body: JSON.stringify({
        session: `/api/sessions/${props.id}`,
        theme: theme['@id'],
        name: newTheme.value.playerName,
      }),
    })
    players.value.push(player)
  }

  newTheme.value = { name: '', color: '', bonus: false, playerName: '' }

  await addBlankQuestions(theme, target)

  // A new normal theme/player combo also means the bonus category needs one more question.
  if (!isBonus && bonusThemes.value.length === 1) {
    await addBlankQuestions(bonusThemes.value[0], 1)
  }
}

async function toggleEasyMode(player, easyMode) {
  const updated = await apiFetch(player['@id'], {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/merge-patch+json' },
    body: JSON.stringify({ easyMode }),
  })
  const index = players.value.findIndex((p) => p['@id'] === player['@id'])
  if (index !== -1) players.value[index] = updated
}

async function removeTheme(theme) {
  const player = playerFor(theme)
  if (player) {
    await apiFetch(player['@id'], { method: 'DELETE' })
    players.value = players.value.filter((p) => p.id !== player.id)
  }
  await apiFetch(theme['@id'], { method: 'DELETE' })
  themes.value = themes.value.filter((t) => t.id !== theme.id)
}

async function addQuestionRound() {
  addingRound.value = true
  try {
    await Promise.all(normalThemes.value.map((t) => addBlankQuestions(t, 1)))
  } finally {
    addingRound.value = false
  }
}

async function addOneQuestion(theme) {
  await addBlankQuestions(theme, 1)
}

async function removeQuestion(question) {
  await apiFetch(question['@id'], { method: 'DELETE' })
  questions.value = questions.value.filter((q) => q.id !== question.id)
}

function markQuestionsDirty() {
  dirtyQuestions.value = true
}

function isIncomplete(question) {
  return !question.questionText.trim() || !question.answerText.trim()
}

const hasIncompleteQuestions = computed(() => questions.value.some(isIncomplete))

async function validateQuestions() {
  savingQuestions.value = true
  try {
    await Promise.all(
      questions.value.map((q) =>
        apiFetch(q['@id'], {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/merge-patch+json' },
          body: JSON.stringify({ questionText: q.questionText, answerText: q.answerText }),
        }),
      ),
    )
    dirtyQuestions.value = false
  } finally {
    savingQuestions.value = false
  }
}

function confirmLeave() {
  return !dirtyQuestions.value || window.confirm('Des questions ne sont pas enregistrées. Quitter quand même ?')
}

function handleBeforeUnload(event) {
  if (dirtyQuestions.value) {
    event.preventDefault()
    event.returnValue = ''
  }
}

onBeforeRouteLeave(() => confirmLeave())

onMounted(() => {
  load()
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})
</script>

<template>
  <div class="page">
    <p v-if="loading" class="empty-state">Chargement...</p>
    <p v-else-if="error" class="empty-state">Erreur : {{ error }}</p>
    <template v-else>
      <div class="topbar">
        <div class="topbar-title">
          <router-link class="back-link" :to="{ name: 'session-list' }">&larr; Sessions</router-link>
          <h1>{{ session.name }}</h1>
        </div>
        <div class="list-item-actions">
          <router-link
            v-if="gameInProgress && readiness.ready"
            class="btn btn-danger"
            :to="{ name: 'session-play', params: { id }, query: { new: '1' } }"
          >
            ↺ Nouvelle partie
          </router-link>
          <router-link v-if="readiness.ready" class="btn btn-primary" :to="{ name: 'session-play', params: { id } }">
            ▶ Jouer
          </router-link>
          <button v-else class="btn btn-primary" type="button" disabled title="Complétez la configuration ci-dessous">
            ▶ Jouer
          </button>
        </div>
      </div>

      <section class="card" :class="readiness.ready ? 'is-ready' : 'is-not-ready'">
        <h2>État de la partie</h2>
        <p v-if="readiness.ready">✓ Prêt à jouer — chacun répondra à {{ readiness.questionsPerPlayer }} questions.</p>
        <ul v-else class="issues">
          <li v-for="issue in readiness.issues" :key="issue">⚠ {{ issue }}</li>
        </ul>
      </section>

      <section class="card">
        <h2>Paramètres</h2>
        <form @submit.prevent="saveParams">
          <label class="field">
            Nom
            <input v-model="session.name" type="text" required />
          </label>
          <div class="field-row">
            <label class="field">
              Durée d'affichage (s)
              <input v-model.number="session.revealDuration" type="number" min="1" required />
            </label>
            <label class="field field-checkbox">
              <input v-model="session.shuffled" type="checkbox" />
              Ordre aléatoire
            </label>
          </div>
          <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
      </section>

      <section class="card">
        <h2>Thèmes &amp; joueurs</h2>
        <ul class="list">
          <li v-for="t in orderedThemes" :key="t.id" class="list-item">
            <span class="badge">
              <ColorDot :color="t.color" />
              {{ t.name }}
              <template v-if="t.bonus">— ⭐ Bonus</template>
              <template v-else-if="playerFor(t)">— {{ playerFor(t).name }}</template>
              ({{ questionCountFor(t) }})
            </span>
            <label
              v-if="!t.bonus && playerFor(t)"
              class="field field-checkbox"
              title="Pendant son tour, les questions de son thème s'affichent en couleur au lieu de noir."
            >
              <input
                type="checkbox"
                :checked="playerFor(t).easyMode"
                @change="toggleEasyMode(playerFor(t), $event.target.checked)"
              />
              Mode facile
            </label>
            <button class="btn btn-danger btn-sm" type="button" @click="removeTheme(t)">Supprimer</button>
          </li>
        </ul>
        <form class="subsection-form" @submit.prevent="addTheme">
          <div class="field-row">
            <label class="field">
              Nom du thème
              <input v-model="newTheme.name" type="text" required />
            </label>
            <label class="field field-checkbox">
              <input v-model="newTheme.bonus" type="checkbox" />
              Catégorie bonus (pas de joueur)
            </label>
            <label v-if="!newTheme.bonus" class="field">
              Nom du joueur
              <input v-model="newTheme.playerName" type="text" required />
            </label>
          </div>
          <div class="field">
            Couleur
            <div class="color-swatches">
              <button
                v-for="c in THEME_COLORS"
                :key="c"
                type="button"
                class="color-swatch"
                :class="{ 'is-selected': newTheme.color === c, 'is-disabled': usedColors.has(c) }"
                :style="{ backgroundColor: c }"
                :disabled="usedColors.has(c)"
                :title="usedColors.has(c) ? 'Déjà utilisée' : c"
                @click="newTheme.color = c"
              ></button>
            </div>
          </div>
          <button class="btn btn-primary" type="submit" :disabled="!newTheme.color">Ajouter</button>
        </form>
      </section>

      <section class="card">
        <h2>Questions</h2>

        <div v-for="t in orderedThemes" :key="t.id" class="theme-questions">
          <h3 class="theme-questions-title">
            <span class="badge">
              <ColorDot :color="t.color" />
              {{ t.name }}<template v-if="t.bonus"> — ⭐ Bonus</template>
            </span>
            <span class="list-item-meta">{{ questionCountFor(t) }} question(s)</span>
          </h3>
          <ul class="list">
            <li v-for="q in questionsFor(t)" :key="q.id" class="list-item question-item">
              <div class="question-inputs">
                <label class="field">
                  Question
                  <textarea
                    v-model="q.questionText"
                    rows="2"
                    placeholder="Question"
                    :class="{ 'is-invalid': !q.questionText.trim() }"
                    @input="markQuestionsDirty"
                  ></textarea>
                </label>
                <label class="field">
                  Réponse
                  <textarea
                    v-model="q.answerText"
                    rows="2"
                    placeholder="Réponse"
                    :class="{ 'is-invalid': !q.answerText.trim() }"
                    @input="markQuestionsDirty"
                  ></textarea>
                </label>
              </div>
              <button class="btn btn-danger btn-sm" type="button" @click="removeQuestion(q)">Supprimer</button>
            </li>
          </ul>
          <button class="btn btn-sm" type="button" @click="addOneQuestion(t)">+ Ajouter une question ici</button>
        </div>

        <div class="list-item-actions">
          <button
            class="btn btn-primary"
            type="button"
            :disabled="!normalThemes.length || addingRound"
            @click="addQuestionRound"
          >
            + Ajouter une question (à chaque thème normal)
          </button>
          <button
            class="btn btn-success"
            type="button"
            :disabled="!readiness.ready || hasIncompleteQuestions || savingQuestions"
            @click="validateQuestions"
          >
            ✓ Valider les questions
          </button>
          <span v-if="dirtyQuestions" class="list-item-meta">Modifications non enregistrées</span>
          <span v-if="hasIncompleteQuestions" class="list-item-meta">Des questions et/ou réponses sont vides</span>
        </div>
      </section>
    </template>
  </div>
</template>
