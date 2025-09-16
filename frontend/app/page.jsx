export default function Home() {
  return (
    <main style={{padding:24,lineHeight:1.6}}>
      <h1 style={{fontSize:'2rem'}}>VeriBits Dashboard</h1>
      <p>Verify files, emails, and transactions. High-contrast and keyboard-friendly.</p>
      <ol>
        <li>Paste an email or file hash</li>
        <li>Get a VeriBit score</li>
        <li>Share a badge or trigger a webhook</li>
      </ol>
      <p style={{opacity:.7}}>© {new Date().getFullYear()} After Dark Systems</p>
    </main>
  )
}
