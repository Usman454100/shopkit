import { useState } from 'react'

/**
 * Minimal placeholder shell — proves the build -> copy -> serve pipeline
 * works end to end (see docs/07-ROADMAP.md Milestone 5). Real admin
 * features (catalog, orders, dashboard) come in a later effort; this only
 * needs to log in against the real API and show who's logged in.
 */
export default function App() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [user, setUser] = useState(() => {
    const stored = localStorage.getItem('shopkit_admin_user')
    return stored ? JSON.parse(stored) : null
  })
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const response = await fetch('/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email, password }),
      })

      const body = await response.json()

      if (!response.ok) {
        throw new Error(body.message || 'Login failed.')
      }

      localStorage.setItem('shopkit_admin_token', body.token)
      localStorage.setItem('shopkit_admin_user', JSON.stringify(body.data))
      setUser(body.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  function handleLogout() {
    localStorage.removeItem('shopkit_admin_token')
    localStorage.removeItem('shopkit_admin_user')
    setUser(null)
  }

  if (user) {
    return (
      <main>
        <h1>ShopKit Store Admin</h1>
        <p>
          Logged in as <strong>{user.name}</strong> ({user.role})
        </p>
        <button onClick={handleLogout}>Log out</button>
      </main>
    )
  }

  return (
    <main>
      <h1>ShopKit Store Admin</h1>
      <form onSubmit={handleSubmit}>
        <label>
          Email
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </label>
        <label>
          Password
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
        </label>
        <button type="submit" disabled={loading}>
          {loading ? 'Logging in…' : 'Log in'}
        </button>
        {error && <p role="alert">{error}</p>}
      </form>
    </main>
  )
}
