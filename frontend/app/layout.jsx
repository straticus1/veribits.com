import './globals.css'

export const metadata = {
  title: 'VeriBits Dashboard - Trust Verification Platform',
  description: 'Verify files, emails, and transactions with VeriBits trust verification system',
}

export default function RootLayout({ children }) {
  return (
    <html lang="en" className="h-full">
      <body className="h-full">
        {children}
      </body>
    </html>
  )
}