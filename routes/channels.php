<?php

// Servlet channels are public (no broadcast auth required).
// The app is already behind the 'auth' middleware,
// and channel names contain userId for isolation.
