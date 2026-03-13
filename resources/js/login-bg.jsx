import React from 'react';
import { createRoot } from 'react-dom/client';
import Beams from './components/Beams';

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('beams-root');
  if (container) {
    const root = createRoot(container);
    root.render(
      <div style={{ width: '100%', height: '100vh', position: 'relative' }}>
        <Beams
          beamWidth={4}
          beamHeight={40}
          beamNumber={30}
          lightColor="#a855f7"
          speed={1.2}
          noiseIntensity={1.2}
          scale={0.12}
          rotation={35}
        />
      </div>
    );
  }
});
