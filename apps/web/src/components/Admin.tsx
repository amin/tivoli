type Props = {
  accessKey: string;
};

export default function Admin({ accessKey: _accessKey }: Props) {
  return (
    <section className="section">
      <div className="section-head">
        <h2>Centralbank Admin</h2>
      </div>
      <p className="section-sub">Welcome to the centralbank dashboard. Admin tools will appear here.</p>
      {/* Add admin functionalities here */}
    </section>
  );
}
