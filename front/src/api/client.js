const API_URL = import.meta.env.VITE_API_URL

export async function apiFetch(path, options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/ld+json',
      ...options.headers,
    },
  })

  if (!response.ok) {
    throw new Error(`API error ${response.status}`)
  }

  return response.status === 204 ? null : response.json()
}
